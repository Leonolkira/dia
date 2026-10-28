<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()->paginate(15)->through(fn ($n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? 'info',
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'url' => $n->data['url'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at,
        ]);
    }

    /** Appelé toutes les 30 s par le site : doit rester léger */
    public function summary(Request $request)
    {
        $user = $request->user();

        $messages = $user->hasRole('admin')
            ? Message::where('from_admin', false)->whereNull('read_at')->count()
            : Message::where('from_admin', true)
                ->whereNull('read_at')
                ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
                ->count();

        return response()->json([
            'notifications_unread' => $user->unreadNotifications()->count(),
            'messages_unread' => $messages,
        ]);
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->first()?->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}