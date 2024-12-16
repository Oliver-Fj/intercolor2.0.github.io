<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\StockAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockReport;

class StockManagementController extends Controller
{
    public function adjustStock(Request $request, $productId)
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer',
                'type' => 'required|in:entrada,salida,ajuste',
                'notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            $previousStock = $product->stock;
            
            // Calcular nuevo stock
            if ($validated['type'] === 'entrada') {
                $product->stock += $validated['quantity'];
            } elseif ($validated['type'] === 'salida') {
                if ($product->stock < $validated['quantity']) {
                    throw new \Exception('Stock insuficiente');
                }
                $product->stock -= $validated['quantity'];
            } else { // ajuste
                $product->stock = $validated['quantity'];
            }

            $product->save();

            // Registrar historial
            $stockHistory = StockHistory::create([
                'product_id' => $product->id,
                'previous_stock' => $previousStock,
                'new_stock' => $product->stock,
                'quantity_changed' => $validated['quantity'],
                'type' => $validated['type'],
                'reference_type' => 'manual',
                'notes' => $validated['notes'],
                'created_by' => auth()->id()
            ]);

            // Verificar alertas
            $this->checkStockAlerts($product);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Stock actualizado exitosamente',
                'data' => [
                    'product' => $product,
                    'stock_history' => $stockHistory
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error ajustando stock: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getStockHistory(Request $request, $productId = null)
    {
        try {
            $query = StockHistory::with(['product', 'createdBy'])
                                ->latest();

            if ($productId) {
                $query->where('product_id', $productId);
            }

            if ($request->has('timeRange')) {
                $date = match($request->timeRange) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'year' => now()->subYear(),
                    default => now()->subWeek()
                };
                $query->where('created_at', '>=', $date);
            }

            $history = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => $history
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo historial: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo historial'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function setStockAlert(Request $request, $productId)
    {
        try {
            $validated = $request->validate([
                'minimum_stock' => 'required|integer|min:0',
                'is_active' => 'boolean'
            ]);

            $alert = StockAlert::updateOrCreate(
                ['product_id' => $productId],
                $validated
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Alerta configurada exitosamente',
                'data' => $alert
            ]);

        } catch (\Exception $e) {
            Log::error('Error configurando alerta: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error configurando alerta'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getLowStockProducts(Request $request)
    {
        try {
            $query = Product::with('stockAlert')
                ->whereHas('stockAlert', function($q) {
                    $q->where('is_active', true);
                })
                ->whereRaw('stock <= (SELECT minimum_stock FROM stock_alerts WHERE stock_alerts.product_id = products.id)');

            $totalProducts = Product::count();
            $products = $query->get();
            $stockRotation = $this->calculateStockRotation();

            return response()->json([
                'status' => 'success',
                'data' => $products,
                'totalProducts' => $totalProducts,
                'stockRotation' => $stockRotation
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo productos con stock bajo: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo productos con stock bajo'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function checkStockAlerts($product)
    {
        $alert = $product->stockAlert;
        if ($alert && $alert->is_active && $product->stock <= $alert->minimum_stock && !$alert->is_notified) {
            // Aquí podrías enviar notificaciones
            $alert->update([
                'is_notified' => true,
                'last_notification' => now()
            ]);
        }
    }

    private function calculateStockRotation()
    {
        try {
            $lastMonth = now()->subMonth();
            
            $totalSales = StockHistory::where('type', 'salida')
                ->where('created_at', '>=', $lastMonth)
                ->sum('quantity_changed');
            
            $averageStock = Product::avg('stock');
            
            if ($averageStock <= 0) return 0;
            
            return round(($totalSales / $averageStock) * 4, 1); // Multiplicamos por 4 para anualizar
        } catch (\Exception $e) {
            Log::error('Error calculando rotación de stock: ' . $e->getMessage());
            return 0;
        }
    }

    public function exportStockReport(Request $request)
    {
        try {
            return Excel::download(new StockReport($request->timeRange), 'reporte-stock.xlsx');
        } catch (\Exception $e) {
            Log::error('Error exportando reporte de stock: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error exportando reporte'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}