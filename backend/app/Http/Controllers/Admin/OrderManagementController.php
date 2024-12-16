<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class OrderManagementController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Order::with(['user', 'items.product'])
                ->latest();

            // Filtro por estado
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filtro por fecha
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Búsqueda por ID de orden o nombre de cliente
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Ordenamiento
            $sortField = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            $orders = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo órdenes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo órdenes'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateStatus(Request $request, $orderId)
    {
        try {
            Log::info('Iniciando actualización de estado', [
                'order_id' => $orderId,
                'request_data' => $request->all()
            ]);

            $validated = $request->validate([
                'status' => ['required', 'string', 'in:pending,processing,shipped,delivered,cancelled'],
                'notes' => ['nullable', 'string']
            ]);

            DB::beginTransaction();

            $order = Order::findOrFail($orderId);
            $oldStatus = $order->status;

            Log::info('Estado actual de la orden', [
                'order_id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $validated['status']
            ]);

            // Actualizar estado
            $order->status = $validated['status'];
            $order->save();

            // Registrar el cambio de estado
            $statusHistory = $order->statusHistories()->create([
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'changed_by' => auth()->id()
            ]);

            // Si se cancela la orden, devolver el stock
            if ($validated['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    $product = $item->product;
                    $product->stock += $item->quantity;
                    $product->save();
                }
            }

            DB::commit();

            Log::info('Estado actualizado exitosamente', [
                'order_id' => $orderId,
                'new_status' => $validated['status']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de orden actualizado exitosamente',
                'data' => [
                    'order' => $order->fresh(['items.product', 'user']),
                    'status_history' => $statusHistory
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Error de validación al actualizar estado', [
                'order_id' => $orderId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando estado de orden: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error actualizando estado de orden: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getStatusHistory($orderId)
    {
        try {
            $order = Order::findOrFail($orderId);

            return response()->json([
                'status' => 'success',
                'data' => $order->statusHistories()
                    ->with('changedBy:id,name')
                    ->orderBy('created_at', 'desc')
                    ->get()
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo historial: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo historial'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getOrderStats()
    {
        try {
            $stats = [
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'processing_orders' => Order::where('status', 'processing')->count(),
                'shipped_orders' => Order::where('status', 'shipped')->count(),
                'delivered_orders' => Order::where('status', 'delivered')->count(),
                'cancelled_orders' => Order::where('status', 'cancelled')->count(),
                'today_orders' => Order::whereDate('created_at', Carbon::today())->count(),
                'total_revenue' => Order::where('status', '!=', 'cancelled')
                    ->sum('total_amount'),
                'today_revenue' => Order::where('status', '!=', 'cancelled')
                    ->whereDate('created_at', Carbon::today())
                    ->sum('total_amount')
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error obteniendo estadísticas'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
