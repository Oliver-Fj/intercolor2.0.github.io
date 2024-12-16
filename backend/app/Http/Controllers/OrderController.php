<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        try {
            Log::info('Iniciando creación de orden con PayPal', [
                'user_id' => $request->user()->id,
                'request_data' => $request->all()
            ]);

            $validated = $request->validate([
                'order_id' => 'required|string',
                'items' => 'required|array',
                'total' => 'required|numeric',
                'status' => 'required|string'
            ]);

            DB::beginTransaction();

            try {
                // Crear orden
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'total_amount' => $validated['total'],
                    'status' => $validated['status'],
                    'paypal_order_id' => $validated['order_id'],
                    'shipping_address' => null,
                    'shipping_city' => null,
                    'shipping_state' => null,
                    'shipping_zip' => null,
                    'notes' => 'Pago realizado con PayPal'
                ]);

                Log::info('Orden creada', ['order_id' => $order->id]);

                // Crear items de la orden
                foreach ($validated['items'] as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'price_at_time' => $item['price']
                    ]);

                    $product = Product::find($item['id']);
                    if ($product) {
                        $product->decrement('stock', $item['quantity']);
                    }
                }

                // Limpiar carrito
                CartItem::where('user_id', $request->user()->id)->delete();

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Orden creada exitosamente',
                    'data' => $order->load('items.product')
                ], Response::HTTP_CREATED);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error en transacción', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error al crear orden', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear orden',
                'debug_info' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function index(Request $request)
    {
        try {
            $orders = Order::where('user_id', $request->user()->id)
                ->with('items.product')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener órdenes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener órdenes'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Order $order, Request $request)
    {
        try {
            if ($order->user_id !== $request->user()->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No autorizado'
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->json([
                'status' => 'success',
                'data' => $order->load('items.product')
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener orden', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener orden'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
