<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CaseAIController extends Controller
{
    public function generate(Request $request)
    {
        // Log da requisição para debug
        \Log::info('Nova requisição de caso clínico:', [
            'timestamp' => $request->timestamp ?? 'não enviado',
            'random' => $request->random ?? 'não enviado',
            'tipoAnimal' => $request->tipoAnimal,
            'areaClinica' => $request->areaClinica,
            'nivel' => $request->nivel,
            'quantidade' => $request->quantidade,
            'headers' => $request->headers->all()
        ]);
        
        $request->validate([
            'tipoAnimal' => 'required|string',
            'areaClinica' => 'required|string',
            'nivel' => 'required|string|in:basico,intermediario,avancado',
            'quantidade' => 'nullable|integer|min:1|max:10',
        ]);

        $apiKey = env('GROQ_API_KEY');
        
        \Log::info('API Key configurada: ' . ($apiKey ? 'Sim' : 'Não'));
        \Log::info('Iniciando chamada para Groq API...');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key da Groq não configurada'
            ], 500);
        }

        $tipoAnimal = $request->tipoAnimal;
        $areaClinica = $request->areaClinica;
        $nivel = $request->nivel;
        $quantidade = $request->quantidade ?? 5;

        $prompt = "
Você é um especialista em medicina veterinária. Gere exatamente 1 caso clínico completo e realista com {$quantidade} questões, seguindo as especificações:

Parâmetros:
- Tipo de Animal: {$tipoAnimal}
- Área Clínica: {$areaClinica}
- Nível de dificuldade: {$nivel}
- Quantidade de questões: {$quantidade}

IMPORTANTE: Responda APENAS com um JSON válido no formato de array, sem explicações ou texto adicional.

Formato esperado (array com 1 caso):
[
  {
    \"id\": \"caso-1\",
    \"titulo\": \"Título descritivo do caso clínico\",
    \"patientInfo\": {
      \"nome\": \"Nome do animal\",
      \"especie\": \"{$tipoAnimal}\",
      \"raca\": \"Raça específica\",
      \"idade\": \"Idade (ex: 5 anos)\",
      \"peso\": \"Peso (ex: 25kg)\",
      \"sexo\": \"Macho/Fêmea\",
      \"proprietario\": \"Nome do proprietário\"
    },
    \"vitalSigns\": {
      \"temperatura\": \"36.5°C\",
      \"frequenciaCardiaca\": \"80 bpm\",
      \"frequenciaRespiratoria\": \"20 rpm\",
      \"pressaoArterial\": \"120/80 mmHg\"
    },
    \"caseSteps\": [
      {
        \"id\": \"step-1\",
        \"title\": \"Anamnese\",
        \"description\": \"Descrição detalhada da situação apresentada pelo proprietário, incluindo queixa principal, histórico e sintomas observados\",
        \"options\": [\"Opção A realista\", \"Opção B realista\", \"Opção C realista\", \"Opção D realista\"],
        \"correctAnswer\": 0,
        \"explanation\": \"Explicação detalhada e educativa da resposta correta, incluindo o porquê das outras opções estarem incorretas\"
      },
      {
        \"id\": \"step-2\",
        \"title\": \"Exame Físico\",
        \"description\": \"Principais achados do exame físico do animal, incluindo inspeção, palpação, ausculta e percussão\",
        \"options\": [\"Achado A\", \"Achado B\", \"Achado C\", \"Achado D\"],
        \"correctAnswer\": 1,
        \"explanation\": \"Explicação detalhada dos achados físicos esperados para esta condição\"
      },
      {
        \"id\": \"step-3\",
        \"title\": \"Exames Complementares\",
        \"description\": \"Quais exames complementares seriam mais indicados para este caso?\",
        \"options\": [\"Exame A\", \"Exame B\", \"Exame C\", \"Exame D\"],
        \"correctAnswer\": 2,
        \"explanation\": \"Justificativa para a escolha dos exames complementares mais adequados\"
      }
    ]
  }
]

INSTRUÇÕES ESPECÍFICAS:
- Gere EXATAMENTE {$quantidade} questões no array caseSteps
- O caso deve ser único e apresentar uma situação realista dentro da área de {$areaClinica}
- O caso deve ser apropriado para o nível {$nivel} (básico = casos simples, intermediário = casos moderados, avançado = casos complexos)
- Todas as informações devem ser clinicamente precisas e realistas
- As opções de resposta devem ser plausíveis e educativas
- As explicações devem ser detalhadas e didáticas
- Use terminologia veterinária apropriada para o nível especificado
- Adapte a complexidade das questões conforme a quantidade escolhida

Tipos de questões sugeridos (varie conforme a quantidade):
- Anamnese e história clínica
- Exame físico e achados clínicos
- Exames complementares
- Diagnóstico diferencial
- Tratamento e prognóstico

Gere 1 caso clínico completo, realista e educativo para {$tipoAnimal} na área de {$areaClinica} com {$quantidade} questões.
";

        try {
            $response = Http::timeout(120)
                ->withoutVerifying() // Desabilita verificação SSL para desenvolvimento
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama3-8b-8192', // Modelo atual disponível
                    'messages' => [
                        ["role" => "system", "content" => "Você é um especialista em medicina veterinária que cria casos clínicos educacionais detalhados. Sempre responda em formato JSON puro com array de casos."],
                        ["role" => "user", "content" => $prompt]
                    ],
                    'max_tokens' => 4000,
                    'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                \Log::error('Erro da API Groq - Status: ' . $response->status());
                \Log::error('Resposta: ' . $response->body());
                return response()->json([
                    'error' => 'Erro ao comunicar com a API da Groq',
                    'details' => $response->body(),
                    'status' => $response->status()
                ], 500);
            }

            \Log::info('Resposta da Groq recebida com sucesso');
            $aiResponse = $response->json();
            $content = $aiResponse['choices'][0]['message']['content'] ?? '';

            // Limpar o conteúdo para garantir que é JSON puro
            $content = trim($content);
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $casesData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Erro ao decodificar JSON da IA',
                    'json_error' => json_last_error_msg(),
                    'raw_content' => $content
                ], 500);
            }

            if (!is_array($casesData)) {
                return response()->json([
                    'error' => 'Formato inválido da resposta da IA - esperado array de casos',
                    'raw_content' => $content
                ], 500);
            }

            // Validar e limpar estrutura dos casos clínicos
            $validCases = [];
            foreach ($casesData as $index => $case) {
                if (is_array($case) && 
                    isset($case['patientInfo']) && 
                    isset($case['caseSteps']) && 
                    is_array($case['caseSteps']) && 
                    !empty($case['caseSteps'])) {
                    
                    // Garantir que cada caso tenha um ID único
                    if (!isset($case['id'])) {
                        $case['id'] = 'caso-' . ($index + 1);
                    }
                    
                    // Garantir que cada caso tenha um título
                    if (!isset($case['titulo'])) {
                        $case['titulo'] = "Caso Clínico " . ($index + 1) . " - {$areaClinica}";
                    }

                    // Garantir que vitalSigns existe
                    if (!isset($case['vitalSigns'])) {
                        $case['vitalSigns'] = [
                            'temperatura' => 'Normal',
                            'frequenciaCardiaca' => 'Normal',
                            'frequenciaRespiratoria' => 'Normal',
                            'pressaoArterial' => 'Normal'
                        ];
                    }

                    // Validar steps básicos
                    $validSteps = [];
                    foreach ($case['caseSteps'] as $stepIndex => $step) {
                        if (isset($step['title']) && isset($step['description']) && 
                            isset($step['options']) && is_array($step['options']) &&
                            isset($step['correctAnswer']) && isset($step['explanation'])) {
                            
                            if (!isset($step['id'])) {
                                $step['id'] = 'step-' . $stepIndex;
                            }
                            
                            $validSteps[] = $step;
                        }
                    }

                    if (!empty($validSteps)) {
                        $case['caseSteps'] = $validSteps;
                        $validCases[] = $case;
                    }
                }
            }

            if (empty($validCases)) {
                return response()->json([
                    'error' => 'Nenhum caso clínico válido foi gerado. Tente novamente.',
                    'raw_content' => $content,
                    'debug_data' => $casesData
                ], 500);
            }

            return response()->json([
                'success' => true,
                'cases' => $validCases,
                'total' => count($validCases),
                'parameters' => [
                    'tipoAnimal' => $tipoAnimal,
                    'areaClinica' => $areaClinica,
                    'nivel' => $nivel,
                    'quantidade' => $quantidade,
                    'questoes_geradas' => !empty($validCases) ? count($validCases[0]['caseSteps']) : 0
                ],
                'message' => 'Caso clínico gerado com sucesso com ' . (!empty($validCases) ? count($validCases[0]['caseSteps']) : 0) . ' questões!'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro na geração de caso clínico: ' . $e->getMessage());
            \Log::error('Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine());
            
            return response()->json([
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
