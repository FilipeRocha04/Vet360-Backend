<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StudyTrailController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'areaEstudo' => 'required|string|max:255',
            'nivelConhecimento' => 'required|string|in:iniciante,intermediario,avancado',
            'tempoDisponivel' => 'required|integer|min:1|max:365',
            'tipoTempo' => 'required|string|in:dias,semanas,meses',
            'horasPorDia' => 'required|integer|min:1|max:12',
            'objetivos' => 'nullable|string|max:1000',
            'preferencias' => 'nullable|string|max:500',
            'recursosPreferidos' => 'nullable|array',
            'recursosPreferidos.*' => 'string|in:livros,artigos,videos,pratica,simulacoes'
        ]);

        $apiKey = env('GROQ_API_KEY');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API key da Groq não configurada'
            ], 500);
        }

        $areaEstudo = $request->input('areaEstudo');
        $nivelConhecimento = $request->input('nivelConhecimento');
        $tempoDisponivel = $request->input('tempoDisponivel');
        $tipoTempo = $request->input('tipoTempo');
        $horasPorDia = $request->input('horasPorDia');
        $objetivos = $request->input('objetivos', 'Não especificado');
        $preferencias = $request->input('preferencias', 'Não especificado');
        $recursosPreferidos = $request->input('recursosPreferidos', []);

        // Calcular tempo total disponível
        $multiplicador = match($tipoTempo) {
            'dias' => 1,
            'semanas' => 7,
            'meses' => 30,
            default => 1
        };
        
        $totalDias = $tempoDisponivel * $multiplicador;
        $totalHoras = $totalDias * $horasPorDia;

        $recursosTexto = empty($recursosPreferidos) ? 'Todos os tipos' : implode(', ', $recursosPreferidos);

        $prompt = "
Você é um especialista em educação veterinária e planejamento de estudos. Crie uma trilha de estudos personalizada e detalhada com base nas seguintes informações:

PERFIL DO ESTUDANTE:
- Área de Estudo: {$areaEstudo}
- Nível de Conhecimento: {$nivelConhecimento}
- Tempo Disponível: {$tempoDisponivel} {$tipoTempo}
- Horas por Dia: {$horasPorDia}h
- Total de Horas: {$totalHoras}h
- Objetivos: {$objetivos}
- Preferências: {$preferencias}
- Recursos Preferidos: {$recursosTexto}

IMPORTANTE: Responda APENAS com um JSON válido, sem explicações ou texto adicional.

Formato obrigatório:
{
  \"resumoTrilha\": {
    \"titulo\": \"Título da trilha de estudos\",
    \"duracao\": \"Duração total estimada\",
    \"nivel\": \"Nível de dificuldade\",
    \"descricao\": \"Descrição geral da trilha\"
  },
  \"etapas\": [
    {
      \"id\": 1,
      \"titulo\": \"Nome da etapa\",
      \"duracao\": \"Duração da etapa\",
      \"horasEstimadas\": \"Horas necessárias\",
      \"descricao\": \"Descrição detalhada da etapa\",
      \"topicos\": [\"Tópico 1\", \"Tópico 2\", \"Tópico 3\"],
      \"atividades\": [\"Atividade 1\", \"Atividade 2\"],
      \"recursos\": [
        {
          \"tipo\": \"livro\",
          \"titulo\": \"Título do livro\",
          \"autor\": \"Autor\",
          \"descricao\": \"Descrição do recurso\"
        },
        {
          \"tipo\": \"artigo\",
          \"titulo\": \"Título do artigo\",
          \"fonte\": \"Fonte\",
          \"descricao\": \"Descrição do recurso\"
        }
      ],
      \"avaliacoes\": [\"Forma de avaliação 1\", \"Forma de avaliação 2\"]
    }
  ],
  \"cronograma\": [
    {
      \"semana\": 1,
      \"etapa\": \"Nome da etapa\",
      \"horasSemanais\": \"Horas por semana\",
      \"atividades\": [\"Atividade semanal 1\", \"Atividade semanal 2\"]
    }
  ],
  \"recursosComplementares\": [
    {
      \"categoria\": \"Livros Fundamentais\",
      \"itens\": [
        {
          \"titulo\": \"Título do livro\",
          \"autor\": \"Autor\",
          \"ano\": \"Ano\",
          \"descricao\": \"Por que é importante\"
        }
      ]
    },
    {
      \"categoria\": \"Artigos Científicos\",
      \"itens\": [
        {
          \"titulo\": \"Título do artigo\",
          \"revista\": \"Nome da revista\",
          \"ano\": \"Ano\",
          \"descricao\": \"Relevância do artigo\"
        }
      ]
    }
  ],
  \"dicasEstudo\": [
    \"Dica 1 para otimizar o estudo\",
    \"Dica 2 para melhor aproveitamento\",
    \"Dica 3 para manter a motivação\"
  ],
  \"marcos\": [
    {
      \"etapa\": \"Etapa do marco\",
      \"objetivo\": \"Objetivo a ser alcançado\",
      \"indicadores\": [\"Indicador 1\", \"Indicador 2\"]
    }
  ]
}

Crie uma trilha de estudos completa, realista e educativa adequada para um estudante de medicina veterinária de nível {$nivelConhecimento}.
";

        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'mixtral-8x7b-32768',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um especialista em educação veterinária e planejamento de estudos. Sempre responda em formato JSON puro.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Erro ao comunicar com a API da Groq',
                    'details' => $response->body()
                ], 500);
            }

            $iaResult = $response->json();
            $generatedContent = $iaResult['choices'][0]['message']['content'] ?? '';

            // Limpar o conteúdo para garantir que é JSON puro
            $generatedContent = trim($generatedContent);
            $generatedContent = preg_replace('/^```json\s*/', '', $generatedContent);
            $generatedContent = preg_replace('/\s*```$/', '', $generatedContent);

            $studyTrail = json_decode($generatedContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Erro ao decodificar JSON gerado pela IA',
                    'rawResponse' => $generatedContent,
                    'jsonError' => json_last_error_msg()
                ], 500);
            }

            return response()->json($studyTrail);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
