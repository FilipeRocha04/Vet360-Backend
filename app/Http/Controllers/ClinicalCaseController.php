<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClinicalCaseController extends Controller
{
    public function generate(Request $request)
    {
        $tipoAnimal = $request->input('tipoAnimal');
        $areaClinica = $request->input('areaClinica');
        $nivel = $request->input('nivel');

      $prompt = "
Gere um caso clínico veterinário no nível {$nivel}, para {$tipoAnimal}, na área de {$areaClinica}.

Formato obrigatório de saída (JSON):

{
  \"patientInfo\": {
    \"nome\": \"Nome do animal\",
    \"especie\": \"Espécie\",
    \"raca\": \"Raça\",
    \"idade\": \"Idade\",
    \"peso\": \"Peso\",
    \"sexo\": \"Sexo\",
    \"proprietario\": \"Nome do proprietário\"
  },
  \"vitalSigns\": {
    \"temperatura\": \"Temperatura\",
    \"frequenciaCardiaca\": \"Frequência Cardíaca\",
    \"frequenciaRespiratoria\": \"Frequência Respiratória\",
    \"pressaoArterial\": \"Pressão Arterial\"
  },
  \"caseSteps\": [
    {
      \"id\": \"identificador_etapa\",
      \"title\": \"Título da etapa\",
      \"description\": \"Descrição detalhada\",
      \"options\": [\"Opção 1\", \"Opção 2\", \"Opção 3\", \"Opção 4\"],
      \"correctAnswer\": 1,
      \"explanation\": \"Explicação da resposta correta\"
    }
  ]
}

Apenas retorne o JSON puro, sem comentários, sem explicações extras.
";


        $response = Http::withHeaders([
            'Authorization' => 'Bearer' . env('GROQ_API_KEY'), // Certifique-se de definir a chave da API no arquivo .env
        ])->post('https://api.groq.com/openai/v1/chat/completions'
, [
            "model" => "llama3-70b-8192",  // Ou GPT-4, ou outro modelo
            "messages" => [
                ["role" => "user", "content" => $prompt],
            ],
            "temperature" => 0.7,
        ]);

        $iaResult = $response->json();
        $generatedContent = $iaResult['choices'][0]['message']['content'] ?? '';

        // Transformar a string JSON da IA em um array PHP
        $generatedCase = json_decode($generatedContent, true);

        return response()->json($generatedCase);
    }
}
