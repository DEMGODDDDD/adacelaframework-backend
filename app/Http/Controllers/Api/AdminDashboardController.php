<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        $revenueStatuses = ['pending', 'processing', 'shipped', 'delivered'];

        $recentOrders = Order::query()
            ->with('user:id,first_name,last_name,email')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Order $order) => [
                'type' => 'order',
                'id' => $order->id,
                'label' => "Order {$order->order_number} placed",
                'detail' => $order->user?->full_name ?? $order->shipping_name,
                'created_at' => $order->created_at?->toISOString(),
            ]);

        $recentReviews = Review::query()
            ->with(['user:id,first_name,last_name', 'product:id,name'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Review $review) => [
                'type' => 'review',
                'id' => $review->id,
                'label' => "New {$review->rating}-star review",
                'detail' => trim(($review->user?->full_name ?? 'Customer').' · '.($review->product?->name ?? 'Product')),
                'created_at' => $review->created_at?->toISOString(),
            ]);

        $activity = $recentOrders->concat($recentReviews)
            ->sortByDesc('created_at')
            ->take(8)
            ->values();

        return response()->json([
            'metrics' => [
                'total_sales' => (float) Order::query()->whereIn('status', $revenueStatuses)->sum('total'),
                'total_orders' => Order::query()->count(),
                'active_products' => Product::query()->where('status', true)->count(),
                'registered_users' => User::query()->count(),
                'low_stock_alerts' => Inventory::query()
                    ->whereColumn('stock_quantity', '<=', 'reorder_level')
                    ->whereHas('product', fn ($query) => $query->where('status', true))
                    ->count(),
            ],
            'recent_activity' => $activity,
            'quick_actions' => [
                ['label' => 'Add product', 'route' => '/admin/products'],
                ['label' => 'Review orders', 'route' => '/admin/orders'],
                ['label' => 'Check low stock', 'route' => '/admin/inventory'],
            ],
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'range' => 'sometimes|in:daily,weekly,monthly',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        $range = $filters['range'] ?? 'monthly';
        $end = isset($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfDay();
        $start = isset($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : match ($range) {
                'daily' => $end->copy()->subDays(6)->startOfDay(),
                'weekly' => $end->copy()->subWeeks(7)->startOfWeek(),
                default => $end->copy()->subMonths(11)->startOfMonth(),
            };

        $dailySales = Order::query()
            ->whereNotIn('status', ['cancelled', 'return_refund'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as sales')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $salesByPeriod = $dailySales->groupBy(function ($row) use ($range): string {
            $date = Carbon::parse($row->date);

            return match ($range) {
                'daily' => $date->format('Y-m-d'),
                'weekly' => $date->startOfWeek()->format('Y-m-d'),
                default => $date->format('Y-m'),
            };
        })->map(fn ($rows, $period) => [
            'period' => $period,
            'sales' => (float) $rows->sum('sales'),
            'orders' => (int) $rows->sum('orders'),
        ])->values();

        $topProducts = OrderItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as units_sold'), DB::raw('SUM(total) as revenue'))
            ->whereHas('order', fn ($query) => $query
                ->whereNotIn('status', ['cancelled', 'return_refund'])
                ->whereBetween('created_at', [$start, $end]))
            ->with('product:id,name,sku')
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->limit(8)
            ->get()
            ->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product?->name ?? 'Deleted product',
                'sku' => $item->product?->sku,
                'units_sold' => (int) $item->units_sold,
                'revenue' => (float) $item->revenue,
            ]);

        return response()->json([
            'range' => $range,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'totals' => [
                'revenue' => (float) $dailySales->sum('sales'),
                'orders' => (int) $dailySales->sum('orders'),
            ],
            'sales' => $salesByPeriod,
            'top_products' => $topProducts,
        ]);
    }
}
