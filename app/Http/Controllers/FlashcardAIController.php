<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Flashcard;

class FlashcardAIController extends Controller
{
    public function generate(Request $request)
    {
        // Log da requisição recebida
        \Log::info('FlashcardAI: Requisição recebida', [
            'data' => $request->all(),
            'headers' => $request->headers->all()
        ]);

            $request->validate([
                'tema' => 'required|string|max:255',
                'quantidade' => 'nullable|integer|min:1|max:20',
                'idioma' => 'nullable|string|in:Português,Inglês,Espanhol'
            ]);

            $apiKey = env('GROQ_API_KEY');
            
            if (!$apiKey) {
                \Log::error('FlashcardAI: API key da Groq não configurada');
                return response()->json([
                    'error' => 'API key da Groq não configurada'
                ], 500);
            }

            $tema = $request->tema;
            $quantidade = $request->quantidade ?? 5;
            $idioma = $request->idioma ?? 'Português';

            \Log::info('FlashcardAI: Parâmetros processados', [
                'tema' => $tema,
                'quantidade' => $quantidade,
                'idioma' => $idioma
            ]);

        $prompt = "
Você é um especialista em educação veterinária. Gere exatamente {$quantidade} flashcards sobre o tema: '{$tema}'.

Cada flashcard deve ter:
- front: Uma pergunta clara e objetiva
- back: Uma resposta completa e precisa
- category: Uma categoria relacionada ao tema

IMPORTANTE: Responda APENAS com um array JSON válido, sem explicações ou texto adicional.

Formato esperado:
[
  {\"front\": \"Pergunta específica?\", \"back\": \"Resposta detalhada e precisa\", \"category\": \"Categoria do tópico\"},
  {\"front\": \"Outra pergunta?\", \"back\": \"Outra resposta completa\", \"category\": \"Categoria relevante\"}
]

Idioma: {$idioma}
Tema: {$tema}
";

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'mixtral-8x7b-32768',
                'messages' => [
                    ["role" => "system", "content" => "Você é um assistente especializado em criar flashcards educacionais para veterinários. Responda sempre em formato JSON puro."],
                    ["role" => "user", "content" => $prompt]
                ],
                'max_tokens' => 4000,
                'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Erro ao comunicar com a API da Groq',
                    'details' => $response->body()
                ], 500);
            }

            $aiResponse = $response->json();
            $content = $aiResponse['choices'][0]['message']['content'] ?? '';

            // Limpar o conteúdo para garantir que é JSON puro
            $content = trim($content);
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $flashcards = json_decode($content, true);

            if (!is_array($flashcards)) {
                return response()->json([
                    'error' => 'Resposta inválida da IA. Tente novamente.',
                    'raw_content' => $content
                ], 500);
            }

            // Validar estrutura dos flashcards
            $validFlashcards = [];
            foreach ($flashcards as $fc) {
                if (isset($fc['front']) && isset($fc['back']) && isset($fc['category'])) {
                    $validFlashcards[] = [
                        'front' => $fc['front'],
                        'back' => $fc['back'],
                        'category' => $fc['category']
                    ];
                }
            }

            if (empty($validFlashcards)) {
                return response()->json([
                    'error' => 'Nenhum flashcard válido foi gerado. Tente novamente.',
                    'raw_content' => $content
                ], 500);
            }

            // Salvar no banco de dados
            foreach ($validFlashcards as $fc) {
                Flashcard::create([
                    'front' => $fc['front'],
                    'back' => $fc['back'],
                    'category' => $fc['category'],
                ]);
            }

            return response()->json($validFlashcards);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('FlashcardAI: Erro de validação', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Dados inválidos',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('FlashcardAI: Erro interno', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Erro interno do servidor',
                'message' => config('app.debug') ? $e->getMessage() : 'Tente novamente em alguns instantes'
            ], 500);
        }
    }
}
