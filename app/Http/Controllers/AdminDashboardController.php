<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        // Chiffre d'affaires : commandes payées et non annulées
        $paid = fn () => Order::where('payment_status', 'paye')->where('status', '!=', 'annulee');

        return response()->json([
            'total_users' => User::count(),
            'total_clients' => User::role('client')->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'orders_by_status' => Order::selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'revenue_total' => (float) $paid()->sum('total'),
            'revenue_this_month' => (float) $paid()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total'),
            'top_products' => \App\Models\OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', '!=', 'annulee')
                ->selectRaw('order_items.product_id, order_items.product_name, sum(order_items.quantity) as total_sold')
                ->groupBy('order_items.product_id', 'order_items.product_name')
                ->orderByDesc('total_sold')
                ->whereNotNull('order_items.product_id')
                ->limit(5)
                ->get(),
            'recent_orders' => Order::with([
                    'user' => fn ($q) => $q->withTrashed()->select('id', 'name'),
                    'vendor:id,name',
                ])
                ->latest()
                ->limit(10)
                ->get(),
                'pending_custom_requests' => \App\Models\CustomRequest::where('status', 'en_attente')->count(),
                'total_reviews' => \App\Models\Review::count(),
        ]);
    }

    public function orders(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:en_attente,confirmee,expediee,livree,annulee',
            'payment_status' => 'nullable|in:non_paye,acompte_paye,paye,echoue',
            'search' => 'nullable|string|max:100',
        ]);

        $query = Order::with([
            'user' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            'vendor:id,name',
            'items',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('search')) {
            $term = ltrim($request->search, '#');
            $query->where(function ($q) use ($term) {
                if (is_numeric($term)) {
                    $q->where('id', (int) $term);
                }
                $q->orWhereHas('user', function ($u) use ($term) {
                    $u->withTrashed()->where('name', 'like', '%' . $term . '%');
                });
            });
        }

        return $query->latest()->paginate(20);
    }

    // Liste admin : voit aussi les produits masqués (ce que /products ne fait pas)
    public function products(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,hidden',
        ]);

        $query = Product::with(['category:id,name,slug', 'images', 'variants'])->latest();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"));
        }
        if ($request->status === 'active') {
            $query->where('is_active', true);
        }
        if ($request->status === 'hidden') {
            $query->where('is_active', false);
        }

        return ProductResource::collection($query->paginate(20));
    }

    public function users(Request $request)
    {
        $request->validate([
            'role' => 'nullable|in:client,vendeur,admin',
            'search' => 'nullable|string|max:100',
        ]);

        $query = User::with('roles')->withCount('orders');

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                  ->orWhere('email', 'like', '%' . $term . '%');
            });
        }

        return $query->latest()->paginate(20);
    }

    public function showUser(User $user)
    {
        $user->load('roles')->loadCount('orders');

        return response()->json([
            'user' => $user,
            'total_spent' => (float) $user->orders()
                ->where('payment_status', 'paye')
                ->where('status', '!=', 'annulee')
                ->sum('total'),
            'orders' => $user->orders()
                ->latest()
                ->limit(10)
                ->get(['id', 'status', 'payment_status', 'total', 'created_at']),
        ]);
    } 

    public function reviews(Request $request)
{
    $request->validate([
        'rating' => 'nullable|integer|min:1|max:5',
        'search' => 'nullable|string|max:100',
    ]);

    $query = \App\Models\Review::with([
        'product:id,name',
        'user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'email'),
    ])->latest();

    if ($request->filled('rating')) {
        $query->where('rating', $request->rating);
    }

    if ($request->filled('search')) {
        $term = $request->search;
        $query->where(function ($q) use ($term) {
            $q->where('comment', 'like', "%{$term}%")
              ->orWhere('title', 'like', "%{$term}%")
              ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%"))
              ->orWhereHas('user', fn ($u) => $u->withTrashed()->where('name', 'like', "%{$term}%"));
        });
    }

    return $query->paginate(20)->through(fn ($r) => [
        'id' => $r->id,
        'rating' => $r->rating,
        'title' => $r->title,
        'comment' => $r->comment,
        'created_at' => $r->created_at,
        'verified_purchase' => $r->order_id !== null,
        'product' => $r->product ? ['id' => $r->product->id, 'name' => $r->product->name] : null,
        'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email] : null,
    ]);
}
}