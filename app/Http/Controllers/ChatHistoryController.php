<?php

namespace App\Http\Controllers;

use App\Models\ChatHistory;
use Illuminate\Http\Request;

class ChatHistoryController extends Controller
{
    // 👉 Listar todos os históricos de um usuário
    public function index($userId)
    {
        $histories = ChatHistory::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                $history->messages = is_string($history->messages)
                    ? json_decode($history->messages, true)
                    : $history->messages;
                return $history;
            });

        return response()->json($histories);
    }

    // 👉 Criar um novo histórico
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'title' => 'nullable|string|max:255',
            'messages' => 'required|array',
        ]);

        $history = ChatHistory::create([
            'user_id' => $request->user_id,
            'title' => $request->title,
            'messages' => $request->messages,
        ]);

        return response()->json($history, 201);
    }

    // 👉 Atualizar mensagens ou título de um histórico
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'messages' => 'nullable|array',
        ]);

        $history = ChatHistory::findOrFail($id);

        if ($request->has('title')) {
            $history->title = $request->title;
        }
        if ($request->has('messages')) {
            $history->messages = $request->messages;
        }

        $history->save();

        return response()->json($history);
    }

    // 👉 Deletar um histórico
    public function destroy($id)
    {
        $history = ChatHistory::findOrFail($id);
        $history->delete();

        return response()->json(['message' => 'Histórico deletado com sucesso!']);
    }

    // 👉 (Opcional) Ver um histórico específico
    public function show($id)
    {
        $history = ChatHistory::findOrFail($id);
        $history->messages = is_string($history->messages)
            ? json_decode($history->messages, true)
            : $history->messages;

        return response()->json($history);
    }
}
