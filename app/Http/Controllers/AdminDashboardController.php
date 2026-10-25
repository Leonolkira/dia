<?php

namespace App\Http\Controllers;

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
            ->limit(5)
            ->get(),
        'recent_orders' => Order::with([
                'user' => fn ($q) => $q->withTrashed()->select('id', 'name'),
                'vendor:id,name',
            ])
            ->latest()
            ->limit(10)
            ->get(),
    ]);
}

   public function orders(Request $request)
{
    $request->validate([
        'status' => 'nullable|in:en_attente,confirmee,expediee,livree,annulee',
        'payment_status' => 'nullable|in:non_paye,paye,echoue',
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
}