<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PrescriptionController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'situacaoClinica' => 'required|string|max:1000',
            'peso' => 'required|numeric|min:0.1|max:1000',
            'especie' => 'required|string|max:100',
            'condicaoClinica' => 'required|string|max:500',
            'raca' => 'nullable|string|max:100',
            'idade' => 'nullable|string|max:50',
            'sexo' => 'nullable|string|in:Macho,Fêmea',
            'alergias' => 'nullable|string|max:500',
            'medicamentosAtuais' => 'nullable|string|max:500',
        ]);

        $apiKey = env('GROQ_API_KEY');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key da Groq não configurada'
            ], 500);
        }

        $situacaoClinica = $request->input('situacaoClinica');
        $peso = $request->input('peso');
        $especie = $request->input('especie');
        $condicaoClinica = $request->input('condicaoClinica');
        $raca = $request->input('raca', 'Não informado');
        $idade = $request->input('idade', 'Não informado');
        $sexo = $request->input('sexo', 'Não informado');
        $alergias = $request->input('alergias', 'Nenhuma conhecida');
        $medicamentosAtuais = $request->input('medicamentosAtuais', 'Nenhum');

        $prompt = "
Você é um veterinário especialista em farmacologia veterinária com expertise em medicina interna, farmacologia clínica e toxicologia veterinária. Gere uma prescrição veterinária completa, detalhada e educativa com base nas informações fornecidas.

INFORMAÇÕES DO PACIENTE:
- Situação Clínica: {$situacaoClinica}
- Espécie: {$especie}
- Raça: {$raca}
- Peso: {$peso} kg
- Idade: {$idade}
- Sexo: {$sexo}
- Condição Clínica: {$condicaoClinica}
- Alergias conhecidas: {$alergias}
- Medicamentos atuais: {$medicamentosAtuais}

IMPORTANTE: Responda APENAS com um JSON válido, sem explicações ou texto adicional. Seja preciso nas dosagens e considere as particularidades da espécie.

Formato obrigatório de saída:
{
  \"diagnosticoPrincipal\": \"Diagnóstico principal baseado nos sintomas\",
  \"gravidadeCaso\": \"Leve/Moderado/Grave\",
  \"medicamentos\": [
    {
      \"nome\": \"Nome comercial e princípio ativo\",
      \"categoria\": \"Categoria farmacológica (ex: Antibiótico, Anti-inflamatório)\",
      \"indicacao\": \"Para que serve este medicamento no caso\",
      \"doseRecomendada\": \"Dose específica baseada no peso (mg/kg ou mg total)\",
      \"doseCalculada\": \"Dose calculada para este paciente específico\",
      \"viaAdministracao\": \"Via de administração detalhada\",
      \"frequencia\": \"Frequência de administração (ex: a cada 8h, 2x ao dia)\",
      \"duracao\": \"Duração do tratamento\",
      \"horarios\": \"Sugestão de horários de administração\",
      \"comAlimento\": \"Se deve ser dado com ou sem alimento\",
      \"observacoes\": \"Observações específicas do medicamento\"
    }
  ],
  \"interacoesMedicamentosas\": [
    \"Lista de possíveis interações com medicamentos atuais\"
  ],
  \"alertasSeguranca\": [
    \"Alertas importantes de segurança\"
  ],
  \"contraindicacoes\": [
    \"Contraindicações importantes para esta espécie/condição\"
  ],
  \"monitoramento\": {
    \"parametros\": [\"Parâmetros que devem ser monitorados\"],
    \"frequencia\": \"Com que frequência monitorar\",
    \"sinaisAlerta\": [\"Sinais que indicam necessidade de intervenção\"]
  },
  \"cuidadosEspeciais\": [
    \"Cuidados específicos durante o tratamento\"
  ],
  \"orientacoesProprietario\": [
    \"Orientações importantes para o proprietário\"
  ],
  \"retorno\": {
    \"prazo\": \"Quando retornar para reavaliação\",
    \"motivo\": \"Por que é importante retornar\"
  },
  \"prognostico\": \"Prognóstico esperado com o tratamento\",
  \"alternativasTerapeuticas\": [
    \"Alternativas caso o tratamento principal não funcione\"
  ]
}

Gere uma prescrição segura, precisa e educativa adequada para um estudante de medicina veterinária.
";

        try {
            \Log::info('Gerando prescrição com IA para: ' . $especie . ' - ' . $peso . 'kg');
            
            $response = Http::timeout(120)
                ->withoutVerifying() // Desabilita verificação SSL para desenvolvimento
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant', // Modelo atualizado que funciona
                    'messages' => [
                        ['role' => 'system', 'content' => 'Você é um veterinário especialista em farmacologia veterinária com 20 anos de experiência. Sempre responda em formato JSON puro, sem explicações extras.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3, // Menor temperatura para respostas mais consistentes
                    'max_tokens' => 3000,
                ]);

            if (!$response->successful()) {
                \Log::error('Erro da API Groq: ' . $response->status() . ' - ' . $response->body());
                return response()->json([
                    'error' => 'Erro ao comunicar com a API da Groq',
                    'details' => $response->body(),
                    'status' => $response->status()
                ], 500);
            }

            \Log::info('Prescrição gerada com sucesso pela IA');

            $iaResult = $response->json();
            $generatedContent = $iaResult['choices'][0]['message']['content'] ?? '';

            // Limpar o conteúdo para garantir que é JSON puro
            $generatedContent = trim($generatedContent);
            $generatedContent = preg_replace('/^```json\s*/', '', $generatedContent);
            $generatedContent = preg_replace('/\s*```$/', '', $generatedContent);

            $prescription = json_decode($generatedContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Erro ao decodificar JSON gerado pela IA',
                    'rawResponse' => $generatedContent,
                    'jsonError' => json_last_error_msg()
                ], 500);
            }

            return response()->json($prescription);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
