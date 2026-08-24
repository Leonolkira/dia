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
        return response()->json([
            'total_users' => User::count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'orders_by_status' => Order::selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'revenue_total' => Order::where('payment_status', 'paye')->sum('total'),
            'revenue_this_month' => Order::where('payment_status', 'paye')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total'),
            'top_products' => \App\Models\OrderItem::selectRaw('product_id, product_name, sum(quantity) as total_sold')
                ->groupBy('product_id', 'product_name')
                ->orderByDesc('total_sold')
                ->limit(5)
                ->get(),
            'recent_orders' => Order::with('user:id,name', 'vendor:id,name')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function orders(Request $request)
    {
        $query = Order::with('user:id,name', 'vendor:id,name', 'items');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate(20);
    }

    public function users(Request $request)
    {
        $query = User::with('roles');

        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        return $query->latest()->paginate(20);
    }
}