<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Notifier;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $conversation = Conversation::where('user_id', $request->user()->id)->first();

        if (! $conversation) {
            return response()->json(['messages' => []]);
        }

        // Ouvrir la conversation = lire les messages de la boutique
        Message::where('conversation_id', $conversation->id)
            ->where('from_admin', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $conversation->messages()->latest()->limit(100)->get()
                ->reverse()->values()->map->toApi(),
        ]);
    }

    public function store(Request $request, Notifier $notifier)
    {
        $user = $request->user();

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'order_id' => 'nullable|integer',
            'custom_request_id' => 'nullable|integer',
        ]);

        if (! Message::contextBelongsTo($user, $validated)) {
            return response()->json(['message' => 'Commande ou demande introuvable.'], 422);
        }

        $conversation = Conversation::firstOrCreate(['user_id' => $user->id]);

        $hadUnread = Message::where('conversation_id', $conversation->id)
            ->where('from_admin', false)
            ->whereNull('read_at')
            ->exists();

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'from_admin' => false,
            'body' => $validated['body'],
            'order_id' => $validated['order_id'] ?? null,
            'custom_request_id' => $validated['custom_request_id'] ?? null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // E-mail à l'admin seulement pour le premier message non lu
        if (! $hadUnread) {
            $notifier->admins(
                'message',
                "Nouveau message de {$user->name}",
                "{$user->name} vous a écrit. Répondez depuis l'espace admin.",
                "/admin/messages/{$user->id}",
                Notifier::MAIL
            );
        }

        return response()->json($message->toApi(), 201);
    }
}