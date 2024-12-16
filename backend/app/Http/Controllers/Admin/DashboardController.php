<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Exports\SalesReport;
use App\Exports\InventoryReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    // Método existente de estadísticas
    public function statistics(Request $request)
    {
        try {
            // Obtener el rango de fechas del request o usar valores por defecto
            $startDate = $request->input('start_date') 
                ? Carbon::parse($request->input('start_date')) 
                : Carbon::now()->subDays(30);
            
            $endDate = $request->input('end_date')
                ? Carbon::parse($request->input('end_date'))
                : Carbon::now();

            // Ventas totales en el período
            $totalSales = Order::whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount');

            // Número total de órdenes
            $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Promedio de venta por orden
            $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

            // Productos más vendidos
            $topProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw('SUM(order_items.quantity) as total_quantity'),
                    DB::raw('SUM(order_items.quantity * order_items.price_at_time) as total_revenue')
                )
                ->groupBy('products.id', 'products.name')
                ->orderBy('total_quantity', 'desc')
                ->take(5)
                ->get();

            // Ventas por día
            $dailySales = Order::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_amount) as total'),
                    DB::raw('COUNT(*) as orders')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Productos con stock bajo
            $lowStockProducts = Product::where('stock', '<=', 10)
                ->select('id', 'name', 'stock')
                ->get();

            // Estadísticas de inventario
            $inventoryStats = [
                'total_products' => Product::count(),
                'out_of_stock' => Product::where('stock', 0)->count(),
                'low_stock' => Product::where('stock', '<=', 10)->count(),
                'total_inventory_value' => Product::sum(DB::raw('stock * price'))
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period' => [
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString()
                    ],
                    'sales' => [
                        'total' => $totalSales,
                        'orders_count' => $totalOrders,
                        'average_order_value' => $averageOrderValue,
                        'daily_sales' => $dailySales
                    ],
                    'products' => [
                        'top_selling' => $topProducts,
                        'low_stock' => $lowStockProducts
                    ],
                    'inventory' => $inventoryStats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en estadísticas de dashboard: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo estadísticas: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Método existente de ingresos por período
    public function revenueByPeriod(Request $request)
    {
        try {
            $period = $request->input('period', 'daily'); // daily, weekly, monthly
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $query = Order::whereBetween('created_at', [$startDate, $endDate]);

            switch ($period) {
                case 'weekly':
                    $revenue = $query->select(
                        DB::raw('WEEK(created_at) as period'),
                        DB::raw('SUM(total_amount) as revenue'),
                        DB::raw('COUNT(*) as orders')
                    )
                    ->groupBy('period')
                    ->get();
                    break;

                case 'monthly':
                    $revenue = $query->select(
                        DB::raw('MONTH(created_at) as period'),
                        DB::raw('SUM(total_amount) as revenue'),
                        DB::raw('COUNT(*) as orders')
                    )
                    ->groupBy('period')
                    ->get();
                    break;

                default: // daily
                    $revenue = $query->select(
                        DB::raw('DATE(created_at) as period'),
                        DB::raw('SUM(total_amount) as revenue'),
                        DB::raw('COUNT(*) as orders')
                    )
                    ->groupBy('period')
                    ->get();
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period_type' => $period,
                    'revenue' => $revenue
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en reporte de ingresos: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo ingresos: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Nuevos métodos para exportación de reportes
    public function exportSalesReport(Request $request)
    {
        try {
            Log::info('Iniciando exportación de reporte de ventas');
            
            $startDate = $request->input('start_date') 
                ? Carbon::parse($request->input('start_date')) 
                : Carbon::now()->subDays(30);
            
            $endDate = $request->input('end_date')
                ? Carbon::parse($request->input('end_date'))
                : Carbon::now();

            Log::info('Generando reporte de ventas', [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            $data = $this->reportService->generateSalesReport($startDate, $endDate);
            
            return Excel::download(
                new SalesReport($data),
                'reporte_ventas_' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );

        } catch (\Exception $e) {
            Log::error('Error en exportación de reporte de ventas: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error generando reporte de ventas: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function exportInventoryReport()
    {
        try {
            Log::info('Iniciando exportación de reporte de inventario');

            $data = $this->reportService->generateInventoryReport();
            
            return Excel::download(
                new InventoryReport($data),
                'reporte_inventario_' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );

        } catch (\Exception $e) {
            Log::error('Error en exportación de reporte de inventario: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error generando reporte de inventario: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}