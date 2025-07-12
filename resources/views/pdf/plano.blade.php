<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Plano de Estudos - {{ $tema ?? 'Veterinária' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #222; }
        h1, h2, h3 { color: #4F46E5; margin-bottom: 0.2em; }
        h1 { font-size: 2em; }
        h2 { font-size: 1.3em; margin-top: 1.2em; }
        h3 { font-size: 1.1em; margin-top: 1em; }
        ul, ol { margin: 0.2em 0 0.7em 1.2em; }
        .section { margin-bottom: 1.5em; }
        .atividade { background: #ede9fe; color: #4F46E5; padding: 2px 8px; border-radius: 6px; font-size: 0.95em; display: inline-block; margin-bottom: 0.3em; }
        .destaque { background: #fef9c3; color: #b45309; padding: 2px 8px; border-radius: 6px; font-size: 0.95em; display: inline-block; margin-bottom: 0.3em; }
        .pergunta { font-weight: bold; margin-bottom: 0.2em; }
        .explicacao { background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 6px; font-size: 0.95em; display: inline-block; margin-bottom: 0.3em; }
        .livro { margin-bottom: 0.7em; }
        .dica { margin-bottom: 0.5em; }
    </style>
</head>
<body>
    <h1>Plano de Estudos: {{ $tema }}</h1>

    @if(isset($plano['weeklyPlan']))
        <h2>Cronograma Semanal</h2>
        @foreach($plano['weeklyPlan'] as $dia)
            <div class="section">
                <h3>{{ $dia['day'] ?? '' }} - {{ $dia['theme'] ?? '' }}</h3>
                <div class="atividade">{{ $dia['activity'] ?? '' }}</div>
                <div>{{ $dia['description'] ?? '' }}</div>
                @if(isset($dia['topics']) && is_array($dia['topics']) && count($dia['topics']))
                    <ul>
                        @foreach($dia['topics'] as $topico)
                            <li>{{ $topico }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    @endif

    @if(isset($plano['reviewQuestions']) && count($plano['reviewQuestions']))
        <h2>Questões de Revisão</h2>
        @foreach($plano['reviewQuestions'] as $q)
            <div class="section">
                <div class="pergunta">{{ $q['question'] }}</div>
                <ol type="A">
                    @foreach($q['options'] as $op)
                        <li>{{ $op }}</li>
                    @endforeach
                </ol>
                @if(isset($q['explanation']))
                    <div class="explicacao">Explicação: {{ $q['explanation'] }}</div>
                @endif
            </div>
        @endforeach
    @endif

    @if(isset($plano['recommendedBooks']) && count($plano['recommendedBooks']))
        <h2>Livros Recomendados</h2>
        @foreach($plano['recommendedBooks'] as $livro)
            <div class="livro">
                <strong>{{ $livro['title'] ?? $livro['nome'] ?? 'Título não informado' }}</strong> <br>
                Autor: {{ $livro['author'] ?? $livro['autor'] ?? '-' }}<br>
                <span class="destaque">{{ $livro['difficulty'] ?? 'Intermediário' }}</span><br>
                <span>{{ $livro['description'] ?? '' }}</span>
            </div>
        @endforeach
    @endif

    @if(isset($plano['studyTips']) && count($plano['studyTips']))
        <h2>Dicas de Estudo</h2>
        <ul>
            @foreach($plano['studyTips'] as $dica)
                <li class="dica">{{ $dica }}</li>
            @endforeach
        </ul>
    @endif

    <div style="margin-top:2em;font-size:0.95em;color:#888;">PDF gerado automaticamente pelo VetAI.</div>
</body>
</html>
