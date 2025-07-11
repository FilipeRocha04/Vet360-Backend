<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class IAClinicalCaseController extends Controller
{
    public function generate(Request $request)
    {
        $apiKey = env('GROQ_API_KEY');

        $filters = $request->validate([
            'tipoAnimal' => 'required|string',
            'areaClinica' => 'required|string',
            'nivel' => 'required|string',
        ]);

        $prompt = "
Gere um caso clínico veterinário no seguinte formato JSON:

{
  \"patientInfo\": {
    \"nome\": \"\",
    \"especie\": \"\",
    \"raca\": \"\",
    \"idade\": \"\",
    \"peso\": \"\",
    \"sexo\": \"\",
    \"proprietario\": \"\"
  },
  \"vitalSigns\": {
    \"temperatura\": \"\",
    \"frequenciaCardiaca\": \"\",
    \"frequenciaRespiratoria\": \"\",
    \"pressaoArterial\": \"\"
  },
  \"caseSteps\": [
    {
      \"id\": \"\",
      \"title\": \"\",
      \"description\": \"\",
      \"options\": [\"\", \"\", \"\", \"\"],
      \"correctAnswer\": 0,
      \"explanation\": \"\"
    },
    ... total de 4 etapas
  ]
}

Filtros:
Tipo de Animal: {$filters['tipoAnimal']}
Área Clínica: {$filters['areaClinica']}
Nível de dificuldade: {$filters['nivel']}
";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.groq.com/openai/v1/chat/completions', [
            'model' => 'llama3-70b-8192',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7
        ]);

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Erro ao chamar a API da Groq',
                'details' => $response->body()
            ], 500);
        }

        $content = $response->json();

        try {
            $generatedJson = $content['choices'][0]['message']['content'];
            return response()->json(json_decode($generatedJson, true));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro no parsing da resposta da IA', 'details' => $e->getMessage()], 500);
        }
    }
}
