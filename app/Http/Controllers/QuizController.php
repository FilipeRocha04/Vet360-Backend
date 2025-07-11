<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QuizController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:200',
            'difficulty' => 'required|string|in:easy,medium,hard,mixed',
            'numberOfQuestions' => 'required|integer|min:1|max:20',
        ]);

        $apiKey = env('GROQ_API_KEY');
        
        \Log::info('API Key configurada: ' . ($apiKey ? 'Sim' : 'Não'));
        \Log::info('Iniciando chamada para Groq API para quiz...');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key da Groq não configurada'
            ], 500);
        }

        $topic = $request->topic;
        $difficulty = $request->difficulty;
        $numberOfQuestions = $request->numberOfQuestions;

        $difficultyText = match($difficulty) {
            'easy' => 'fácil - adequado para estudantes iniciantes',
            'medium' => 'médio - adequado para estudantes intermediários',
            'hard' => 'difícil - adequado para estudantes avançados e profissionais',
            'mixed' => 'misto - variando entre fácil, médio e difícil'
        };

        $prompt = "
Você é um especialista em medicina veterinária e educação. Gere exatamente {$numberOfQuestions} perguntas de múltipla escolha sobre o tema: \"{$topic}\".

Parâmetros:
- Tema: {$topic}
- Nível de dificuldade: {$difficultyText}
- Número de questões: {$numberOfQuestions}

INSTRUÇÕES IMPORTANTES:
1. Crie perguntas REAIS e EDUCATIVAS sobre medicina veterinária
2. Cada pergunta deve ter 4 alternativas (A, B, C, D)
3. Apenas UMA alternativa deve estar correta
4. Inclua uma explicação educativa detalhada para cada resposta
5. As perguntas devem ser específicas sobre o tema solicitado
6. Varie o tipo de pergunta: diagnóstico, tratamento, anatomia, fisiologia, etc.

IMPORTANTE: Responda APENAS com um JSON válido no formato abaixo, sem explicações ou texto adicional:

{
  \"questions\": [
    {
      \"id\": \"q1\",
      \"question\": \"Pergunta específica sobre {$topic}\",
      \"options\": [
        \"Alternativa A\",
        \"Alternativa B\", 
        \"Alternativa C\",
        \"Alternativa D\"
      ],
      \"correctAnswer\": 0,
      \"explanation\": \"Explicação detalhada sobre por que esta é a resposta correta e informações educativas relevantes.\",
      \"difficulty\": \"easy|medium|hard\",
      \"category\": \"Categoria da pergunta\"
    }
  ]
}

Agora gere as {$numberOfQuestions} perguntas sobre: {$topic}
";

        try {
            \Log::info('Enviando prompt para Groq API (Quiz):', ['prompt_length' => strlen($prompt)]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->withoutVerifying() // Desabilita verificação SSL temporariamente
            ->timeout(120)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-70b-versatile',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 4000,
                'temperature' => 0.7
            ]);

            \Log::info('Resposta da Groq API (Quiz):', [
                'status' => $response->status(),
                'body_length' => strlen($response->body())
            ]);

            if (!$response->successful()) {
                \Log::error('Erro na resposta da Groq API (Quiz):', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'error' => 'Erro na API da Groq',
                    'details' => $response->body(),
                    'status' => $response->status()
                ], 500);
            }

            $apiResponse = $response->json();
            
            if (!isset($apiResponse['choices'][0]['message']['content'])) {
                \Log::error('Formato de resposta inválido da Groq API (Quiz):', $apiResponse);
                return response()->json([
                    'error' => 'Formato de resposta inválido da API',
                    'response' => $apiResponse
                ], 500);
            }

            $content = $apiResponse['choices'][0]['message']['content'];
            
            \Log::info('Conteúdo bruto da resposta (Quiz):', ['content' => $content]);

            // Tentar extrair JSON da resposta
            $content = trim($content);
            
            // Remove markdown code blocks se existirem
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
            $content = trim($content);

            $decodedContent = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('Erro ao decodificar JSON (Quiz):', [
                    'error' => json_last_error_msg(),
                    'content' => $content
                ]);
                
                return response()->json([
                    'error' => 'Erro ao processar resposta da IA',
                    'json_error' => json_last_error_msg(),
                    'content' => $content
                ], 500);
            }

            // Validar estrutura da resposta
            if (!isset($decodedContent['questions']) || !is_array($decodedContent['questions'])) {
                \Log::error('Estrutura de resposta inválida (Quiz):', $decodedContent);
                return response()->json([
                    'error' => 'Estrutura de resposta inválida da IA',
                    'content' => $decodedContent
                ], 500);
            }

            \Log::info('Quiz gerado com sucesso!', [
                'questions_count' => count($decodedContent['questions']),
                'topic' => $topic
            ]);

            return response()->json($decodedContent);

        } catch (\Exception $e) {
            \Log::error('Erro ao gerar quiz:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
