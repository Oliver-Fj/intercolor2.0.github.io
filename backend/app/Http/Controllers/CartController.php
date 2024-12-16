<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function index(Request $request)
    {
        try {
            Log::info('Consultando carrito', [
                'user_id' => $request->user()->id
            ]);

            $cartItems = CartItem::where('user_id', $request->user()->id)
                               ->with('product')
                               ->get();

            $total = $cartItems->sum(function($item) {
                return $item->quantity * $item->price_at_time;
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'items' => $cartItems,
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en carrito', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el carrito'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addToCart(Request $request)
    {
        try {
            Log::info('=== Iniciando agregado al carrito ===');
            Log::info('Datos recibidos:', $request->all());
    
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1'
            ]);
    
            $product = Product::findOrFail($validated['product_id']);
            
            // Buscar si el producto ya existe en el carrito del usuario
            $existingCartItem = CartItem::where('user_id', $request->user()->id)
                                      ->where('product_id', $product->id)
                                      ->first();
    
            if ($existingCartItem) {
                // Si existe, actualizar la cantidad
                $newQuantity = $existingCartItem->quantity + $validated['quantity'];
                
                // Verificar stock con la nueva cantidad total
                if ($product->stock < $newQuantity) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Stock insuficiente'
                    ], Response::HTTP_BAD_REQUEST);
                }
    
                $existingCartItem->update([
                    'quantity' => $newQuantity
                ]);
    
                Log::info('Cantidad actualizada en item existente', [
                    'cart_item' => $existingCartItem->toArray()
                ]);
    
                return response()->json([
                    'status' => 'success',
                    'message' => 'Cantidad actualizada en el carrito',
                    'data' => $existingCartItem->load('product')
                ]);
    
            } else {
                // Si no existe, verificar stock y crear nuevo item
                if ($product->stock < $validated['quantity']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Stock insuficiente'
                    ], Response::HTTP_BAD_REQUEST);
                }
    
                $cartItem = CartItem::create([
                    'user_id' => $request->user()->id,
                    'product_id' => $product->id,
                    'quantity' => $validated['quantity'],
                    'price_at_time' => $product->price
                ]);
    
                Log::info('Nuevo item agregado al carrito', [
                    'cart_item' => $cartItem->toArray()
                ]);
    
                return response()->json([
                    'status' => 'success',
                    'message' => 'Producto agregado al carrito',
                    'data' => $cartItem->load('product')
                ], Response::HTTP_CREATED);
            }
    
        } catch (\Exception $e) {
            Log::error('=== Error agregando al carrito ===');
            Log::error('Mensaje de error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al agregar al carrito'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }    

// Para el error 404 en update y delete, actualicemos la ruta model binding:
public function updateQuantity(Request $request, $cartItemId)
{
    try {
        $cartItem = CartItem::where('user_id', $request->user()->id)
                           ->where('id', $cartItemId)
                           ->firstOrFail();

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        // Verificar stock
        if ($cartItem->product->stock < $validated['quantity']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stock insuficiente'
            ], Response::HTTP_BAD_REQUEST);
        }

        $cartItem->update([
            'quantity' => $validated['quantity']
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cantidad actualizada',
            'data' => $cartItem->load('product')
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error al actualizar cantidad',
            'error' => $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

public function removeFromCart(Request $request, $cartItemId)
{
    try {
        $cartItem = CartItem::where('user_id', $request->user()->id)
                           ->where('id', $cartItemId)
                           ->firstOrFail();

        $cartItem->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Producto eliminado del carrito'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error al eliminar producto',
            'error' => $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

public function clearCart(Request $request)
{
    try {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Carrito vaciado exitosamente'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error al vaciar el carrito'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

}