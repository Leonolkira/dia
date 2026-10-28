<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomRequestResource;
use App\Models\CustomRequest;
use Illuminate\Http\Request;
use App\Services\Notifier;

class AdminCustomRequestController extends Controller
{
    private const OPEN = ['en_attente', 'devis_envoye'];

    public function index(Request $request)
    {
        $request->validate([
    'status' => 'nullable|in:' . implode(',', [...CustomRequest::STATUSES, 'expiree']),
    'search' => 'nullable|string|max:100',
]);

        $query = CustomRequest::with($this->relations())->latest();

        if ($request->status === 'expiree') {
    $query->where('status', 'devis_envoye')->whereNotNull('valid_until')->where('valid_until', '<', now());
} elseif ($request->filled('status')) {
    $query->where('status', $request->status);
}

        if ($request->filled('search')) {
            $term = ltrim($request->search, '#');
            $query->where(function ($q) use ($term) {
                if (is_numeric($term)) {
                    $q->where('id', (int) $term);
                }
                $q->orWhere('title', 'like', "%{$term}%")
                  ->orWhereHas('user', fn ($u) => $u->withTrashed()->where('name', 'like', "%{$term}%"));
            });
        }

        return CustomRequestResource::collection($query->paginate(20));
    }

    public function show(CustomRequest $customRequest)
    {
        return new CustomRequestResource($customRequest->load($this->relations()));
    }

    /** Envoie (ou met à jour) le devis */
    public function quote(Request $request, CustomRequest $customRequest, Notifier $notifier)
{
    $validated = $request->validate([
        'quoted_price' => 'required|numeric|min:1|max:99999999',
        'lead_time_days' => 'nullable|integer|min:1|max:365',
        'valid_days' => 'nullable|integer|min:1|max:90',
        'admin_note' => 'nullable|string|max:1000',
    ]);

    $updated = CustomRequest::whereKey($customRequest->id)
        ->whereIn('status', self::OPEN)
        ->update([
            'status' => 'devis_envoye',
            'quoted_price' => $validated['quoted_price'],
            'lead_time_days' => $validated['lead_time_days'] ?? null,
            'admin_note' => $validated['admin_note'] ?? null,
            'quoted_at' => now(),
            'valid_until' => now()->addDays($validated['valid_days'] ?? 7)->endOfDay(),
        ]);

    if ($updated === 0) {
        return response()->json(['message' => 'Cette demande ne peut plus être chiffrée.'], 422);
    }

    $customRequest->refresh();
    $notifier->quoteSent($customRequest);

    return new CustomRequestResource($customRequest->load($this->relations()));
}

    /** La boutique ne peut pas réaliser la demande */
    public function reject(Request $request, CustomRequest $customRequest)
    {
        $validated = $request->validate(['admin_note' => 'required|string|max:1000']);

        $updated = CustomRequest::whereKey($customRequest->id)
            ->whereIn('status', self::OPEN)
            ->update(['status' => 'rejetee', 'admin_note' => $validated['admin_note']]);

        if ($updated === 0) {
            return response()->json(['message' => 'Cette demande ne peut plus être refusée.'], 422);
        }

        return new CustomRequestResource($customRequest->refresh()->load($this->relations()));

        $customRequest->refresh();
        app(Notifier::class)->requestRejected($customRequest);

        return new CustomRequestResource($customRequest->load($this->relations()));
    }

    private function relations(): array
    {
        return [
            'product:id,name',
            'user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'email', 'phone'),
        ];
    }
}