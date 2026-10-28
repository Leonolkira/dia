<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminConversationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'unread' => 'nullable|boolean',
        ]);

        $unread = fn ($q) => $q->where('from_admin', false)->whereNull('read_at');

        $query = Conversation::with(['user:id,name,email', 'latestMessage'])
            ->withCount(['messages as unread_count' => $unread])
            ->orderByDesc('last_message_at');

        if ($request->boolean('unread')) {
            $query->whereHas('messages', $unread);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->whereHas('user', fn ($u) => $u->withTrashed()->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
            ));
        }

        return $query->paginate(20)->through(fn ($c) => [
            'user_id' => $c->user_id,
            'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'email' => $c->user->email] : null,
            'unread_count' => (int) $c->unread_count,
            'last_message_at' => $c->last_message_at,
            'last_message' => $c->latestMessage ? [
                'body' => Str::limit($c->latestMessage->body, 90),
                'from_admin' => $c->latestMessage->from_admin,
            ] : null,
        ]);
    }

    public function show($userId)
    {
        $user = User::withTrashed()->findOrFail($userId);
        $conversation = Conversation::where('user_id', $user->id)->first();

        $messages = [];

        if ($conversation) {
            Message::where('conversation_id', $conversation->id)
                ->where('from_admin', false)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $messages = $conversation->messages()->latest()->limit(100)->get()
                ->reverse()->values()->map->toApi();
        }

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, $userId, Notifier $notifier)
    {
        $admin = $request->user();
        $customer = User::findOrFail($userId);

        if ($customer->id === $admin->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous écrire à vous-même.'], 422);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'order_id' => 'nullable|integer',
            'custom_request_id' => 'nullable|integer',
        ]);

        if (! Message::contextBelongsTo($customer, $validated)) {
            return response()->json(['message' => "Cette commande ou demande n'appartient pas à ce client."], 422);
        }

        $conversation = Conversation::firstOrCreate(['user_id' => $customer->id]);

        $hadUnread = Message::where('conversation_id', $conversation->id)
            ->where('from_admin', true)
            ->whereNull('read_at')
            ->exists();

        $message = $conversation->messages()->create([
            'sender_id' => $admin->id,
            'from_admin' => true,
            'body' => $validated['body'],
            'order_id' => $validated['order_id'] ?? null,
            'custom_request_id' => $validated['custom_request_id'] ?? null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        if (! $hadUnread) {
            $notifier->user(
                $customer,
                'message',
                'Nouveau message de ' . config('app.name'),
                'La boutique vous a répondu. Consultez vos messages.',
                '/messages',
                Notifier::MAIL
            );
        }

        return response()->json($message->toApi(), 201);
    }
}