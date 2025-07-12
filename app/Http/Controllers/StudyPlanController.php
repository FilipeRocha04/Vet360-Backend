<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StudyPlanController extends Controller
{
    public function generateStudyPlan(Request $request): JsonResponse
    {
        try {
            // Validação dos dados de entrada
            $validated = $request->validate([
                'tema' => 'required|string|max:500',
                'dias_por_semana' => 'required|integer|min:1|max:7',
                'horas_por_dia' => 'required|numeric|min:0.5|max:12',
                'nivel' => 'required|string|in:iniciante,intermediario,avancado,especializacao',
                'semanas_totais' => 'required|integer|min:1|max:52',
                'preferencias' => 'required|array',
                'idioma' => 'string|max:50'
            ]);

            $apiKey = env('GROQ_API_KEY');
            
            if (!$apiKey) {
                return response()->json([
                    'error' => 'API key não configurada'
                ], 500);
            }

            // Preparar o prompt para a IA
            $prompt = $this->buildStudyPlanPrompt($validated);

            // Fazer a requisição para a API do Groq
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama3-70b-8192',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um especialista em educação veterinária e criação de planos de estudo personalizados. Sua resposta deve ser sempre em JSON válido.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 3000,
            ]);

            if (!$response->successful()) {
                Log::error('Erro na API Groq', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'error' => 'Erro na comunicação com a IA: ' . $response->body()
                ], $response->status());
            }

            $data = $response->json();
            
            if (!isset($data['choices'][0]['message']['content'])) {
                return response()->json([
                    'error' => 'Resposta inválida da IA'
                ], 500);
            }

            $content = $data['choices'][0]['message']['content'];
            
            // Tentar extrair JSON da resposta
            $jsonStart = strpos($content, '{');
            $jsonEnd = strrpos($content, '}');
            
            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonContent = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
                $studyPlan = json_decode($jsonContent, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Garantir que o plano tenha a estrutura esperada
                    $studyPlan = $this->validateStudyPlanStructure($studyPlan);
                    
                    return response()->json($studyPlan);
                }
            }

            // Se não conseguiu parsear JSON, retornar erro
            Log::error('Erro ao parsear JSON da IA', ['content' => $content]);
            
            return response()->json([
                'error' => 'Erro ao processar resposta da IA'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar plano de estudos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    private function buildStudyPlanPrompt(array $data): string
    {
        $preferencesText = '';
        if (isset($data['preferencias'])) {
            $activePreferences = [];
            foreach ($data['preferencias'] as $key => $value) {
                if ($value) {
                    $activePreferences[] = $this->translatePreference($key);
                }
            }
            $preferencesText = implode(', ', $activePreferences);
        }

        return "Crie um plano de estudos detalhado em medicina veterinária com as seguintes especificações:

TEMA: {$data['tema']}
NÍVEL: {$data['nivel']}
DURAÇÃO: {$data['semanas_totais']} semanas, {$data['dias_por_semana']} dias por semana, {$data['horas_por_dia']} horas por dia
FORMATOS PREFERIDOS: {$preferencesText}

Retorne APENAS um JSON válido com esta estrutura exata:

{
  \"weeklyPlan\": [
    {
      \"day\": \"Segunda-feira - Semana 1\",
      \"theme\": \"Anatomia Cardiovascular\",
      \"description\": \"Estudo das estruturas cardíacas e vasculares\",
      \"duration\": \"2h\",
      \"activity\": \"Leitura e diagramas\"
    }
  ],
  \"reviewQuestions\": [
    {
      \"question\": \"Qual é a principal função do ventrículo esquerdo?\",
      \"options\": [\"Bombear sangue para o corpo\", \"Receber sangue venoso\", \"Filtrar o sangue\", \"Produzir hemácias\"],
      \"correctAnswer\": 0,
      \"explanation\": \"O ventrículo esquerdo bombeia sangue oxigenado para todo o corpo através da aorta.\"
    }
  ],
  \"recommendedBooks\": [
    {
      \"title\": \"Cardiologia Veterinária\",
      \"author\": \"Dr. João Silva\",
      \"description\": \"Livro completo sobre cardiologia em pequenos animais\",
      \"difficulty\": \"Intermediário\"
    }
  ],
  \"studyTips\": [
    \"Use diagramas para memorizar anatomia\",
    \"Pratique ausculta cardíaca diariamente\"
  ]
}

Crie um plano progressivo, com pelo menos 7 dias de estudo, 5 questões de múltipla escolha, 3 livros recomendados e 6 dicas práticas. Foque em conteúdo de medicina veterinária de alta qualidade e relevância clínica.";
    }

    private function translatePreference(string $key): string
    {
        $translations = [
            'clinicalCases' => 'casos clínicos',
            'scientificArticles' => 'artigos científicos',
            'flashcards' => 'flashcards',
            'videos' => 'vídeos educativos',
            'multipleChoice' => 'questões de múltipla escolha',
            'books' => 'livros especializados'
        ];

        return $translations[$key] ?? $key;
    }

    private function validateStudyPlanStructure(array $plan): array
    {
        // Garantir que todas as seções necessárias existam
        if (!isset($plan['weeklyPlan'])) {
            $plan['weeklyPlan'] = [];
        }
        
        if (!isset($plan['reviewQuestions'])) {
            $plan['reviewQuestions'] = [];
        }
        
        if (!isset($plan['recommendedBooks'])) {
            $plan['recommendedBooks'] = [];
        }
        
        if (!isset($plan['studyTips'])) {
            $plan['studyTips'] = [];
        }

        return $plan;
    }

    public function generateStudyPlanPDF(Request $request): \Illuminate\Http\Response
    {
        try {
            $validated = $request->validate([
                'plano' => 'required|array',
                'tema' => 'required|string'
            ]);

            $studyPlan = $validated['plano'];
            $theme = $validated['tema'];

            // Gerar HTML para o PDF
            $html = $this->generatePDFHTML($studyPlan, $theme);

            // Para este exemplo, vamos retornar um JSON com o HTML
            // Em produção, você pode usar uma biblioteca como DomPDF ou wkhtmltopdf
            return response()->json([
                'html' => $html,
                'message' => 'PDF gerado com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar PDF', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Erro ao gerar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generatePDFHTML(array $studyPlan, string $theme): string
    {
        $weeklyPlan = $studyPlan['weeklyPlan'] ?? [];
        $questions = $studyPlan['reviewQuestions'] ?? [];
        $books = $studyPlan['recommendedBooks'] ?? [];
        $tips = $studyPlan['studyTips'] ?? [];

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Plano de Estudos - ' . htmlspecialchars($theme) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { text-align: center; background: linear-gradient(135deg, #7C3AED, #3B82F6); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
                .header h1 { margin: 0; font-size: 28px; }
                .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.9; }
                .section { margin-bottom: 30px; }
                .section h2 { color: #7C3AED; border-bottom: 2px solid #E5E7EB; padding-bottom: 10px; }
                .day-item { background: #F9FAFB; padding: 15px; margin-bottom: 10px; border-left: 4px solid #7C3AED; border-radius: 5px; }
                .day-title { font-weight: bold; color: #1F2937; }
                .question { background: #F3F4F6; padding: 15px; margin-bottom: 15px; border-radius: 8px; }
                .option { padding: 5px 10px; margin: 5px 0; }
                .correct { background: #D1FAE5; border-left: 3px solid #10B981; }
                .book { background: #EFF6FF; padding: 15px; margin-bottom: 10px; border-radius: 8px; border-left: 4px solid #3B82F6; }
                .tip { background: #FEF3FF; padding: 10px; margin-bottom: 8px; border-radius: 5px; border-left: 3px solid #A855F7; }
                .footer { text-align: center; margin-top: 40px; padding: 20px; background: #F9FAFB; border-radius: 10px; color: #6B7280; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🩺 Plano de Estudos Veterinário</h1>
                <p>' . htmlspecialchars($theme) . '</p>
                <p>Gerado por Vet360 AI • ' . date('d/m/Y') . '</p>
            </div>';

        // Plano semanal
        if (!empty($weeklyPlan)) {
            $html .= '<div class="section">
                <h2>📅 Cronograma de Estudos</h2>';
            
            foreach ($weeklyPlan as $day) {
                $html .= '<div class="day-item">
                    <div class="day-title">' . htmlspecialchars($day['day'] ?? '') . ' - ' . htmlspecialchars($day['duration'] ?? '') . '</div>
                    <strong>' . htmlspecialchars($day['theme'] ?? '') . '</strong><br>
                    ' . htmlspecialchars($day['description'] ?? '') . '<br>
                    <em>Atividade: ' . htmlspecialchars($day['activity'] ?? '') . '</em>
                </div>';
            }
            $html .= '</div>';
        }

        // Questões
        if (!empty($questions)) {
            $html .= '<div class="section">
                <h2>❓ Questões de Revisão</h2>';
            
            foreach ($questions as $index => $question) {
                $html .= '<div class="question">
                    <strong>' . ($index + 1) . '. ' . htmlspecialchars($question['question'] ?? '') . '</strong><br><br>';
                
                if (isset($question['options'])) {
                    foreach ($question['options'] as $optIndex => $option) {
                        $isCorrect = ($optIndex === ($question['correctAnswer'] ?? -1));
                        $class = $isCorrect ? 'option correct' : 'option';
                        $letter = chr(65 + $optIndex);
                        $html .= '<div class="' . $class . '">' . $letter . ') ' . htmlspecialchars($option) . '</div>';
                    }
                }
                
                if (isset($question['explanation'])) {
                    $html .= '<br><strong>Explicação:</strong> ' . htmlspecialchars($question['explanation']) . '';
                }
                
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Livros
        if (!empty($books)) {
            $html .= '<div class="section">
                <h2>📚 Livros Recomendados</h2>';
            
            foreach ($books as $book) {
                $html .= '<div class="book">
                    <strong>' . htmlspecialchars($book['title'] ?? '') . '</strong><br>
                    <em>Autor: ' . htmlspecialchars($book['author'] ?? '') . '</em><br>
                    ' . htmlspecialchars($book['description'] ?? '') . '<br>
                    <small>Nível: ' . htmlspecialchars($book['difficulty'] ?? 'Intermediário') . '</small>
                </div>';
            }
            $html .= '</div>';
        }

        // Dicas
        if (!empty($tips)) {
            $html .= '<div class="section">
                <h2>💡 Dicas de Estudo</h2>';
            
            foreach ($tips as $index => $tip) {
                $html .= '<div class="tip">' . ($index + 1) . '. ' . htmlspecialchars($tip) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '
            <div class="footer">
                <p><strong>Vet360</strong> - Plataforma de Estudos Veterinários com Inteligência Artificial</p>
                <p>Este plano foi gerado automaticamente. Ajuste conforme sua evolução nos estudos.</p>
            </div>
        </body>
        </html>';

        return $html;
    }
}
