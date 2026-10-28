<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'from_admin', 'body', 'order_id', 'custom_request_id', 'read_at',
    ];

    protected $casts = ['from_admin' => 'boolean', 'read_at' => 'datetime'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'from_admin' => $this->from_admin,
            'body' => $this->body,
            'created_at' => $this->created_at,
            'read' => $this->read_at !== null,
            'order_id' => $this->order_id,
            'custom_request_id' => $this->custom_request_id,
        ];
    }

    /** La commande ou la demande rattachée doit appartenir au client concerné */
    public static function contextBelongsTo(User $user, array $data): bool
    {
        if (! empty($data['order_id'])
            && ! Order::where('id', $data['order_id'])->where('user_id', $user->id)->exists()) {
            return false;
        }

        if (! empty($data['custom_request_id'])
            && ! CustomRequest::where('id', $data['custom_request_id'])->where('user_id', $user->id)->exists()) {
            return false;
        }

        return true;
    }
}