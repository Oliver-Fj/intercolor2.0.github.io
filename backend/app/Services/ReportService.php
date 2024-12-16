<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generateSalesReport($startDate, $endDate)
    {
        return Order::with(['items.product', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($order) {
                return [
                    'order_id' => $order->id,
                    'date' => $order->created_at->format('Y-m-d'),
                    'customer' => $order->user->name,
                    'total_amount' => $order->total_amount,
                    'items_count' => $order->items->count(),
                    'status' => $order->status
                ];
            });
    }

    public function generateInventoryReport()
    {
        return Product::select(
            'id',
            'name',
            'price',
            'stock',
            DB::raw('price * stock as total_value'),
            'created_at',
            'updated_at'
        )->get();
    }

    public function generateProductPerformanceReport($startDate, $endDate)
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price_at_time) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as number_of_orders')
            )
            ->groupBy('products.id', 'products.name')
            ->get();
    }
}