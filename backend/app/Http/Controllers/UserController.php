<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function dashboard()
    {
        try {
            Log::info('Iniciando dashboard');
            $user = Auth::user();
            Log::info('Usuario autenticado:', ['user' => $user?->toArray()]);

            if (!$user) {
                Log::warning('Usuario no autenticado intentando acceder al dashboard');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            try {
                // Obtener estadísticas del usuario con manejo de errores
                $stats = [
                    'total_orders' => DB::table('orders')->where('user_id', $user->id)->count(),
                    'in_process' => DB::table('orders')
                        ->where('user_id', $user->id)
                        ->where('status', 'in_process')
                        ->count(),
                    'delivered' => DB::table('orders')
                        ->where('user_id', $user->id)
                        ->where('status', 'delivered')
                        ->count()
                ];

                Log::info('Estadísticas obtenidas:', $stats);

                // Obtener pedidos recientes con manejo de errores
                $recent_orders = Order::where('user_id', $user->id)
                    ->with(['products'])
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'total' => $order->total,
                            'products_count' => $order->products->count(),
                            'status' => $order->status,
                            'created_at' => $order->created_at->diffForHumans()
                        ];
                    });

                Log::info('Pedidos recientes obtenidos:', ['count' => $recent_orders->count()]);

                $response_data = [
                    'status' => 'success',
                    'data' => [
                        'user' => [
                            'name' => $user->name,
                            'email' => $user->email
                        ],
                        'stats' => $stats,
                        'recent_orders' => $recent_orders
                    ]
                ];

                Log::info('Respuesta preparada exitosamente');
                return response()->json($response_data);
            } catch (\Exception $e) {
                Log::error('Error procesando datos del dashboard:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Intentar devolver al menos los datos básicos del usuario
                return response()->json([
                    'status' => 'partial',
                    'data' => [
                        'user' => [
                            'name' => $user->name,
                            'email' => $user->email
                        ],
                        'stats' => [
                            'total_orders' => 0,
                            'in_process' => 0,
                            'delivered' => 0
                        ],
                        'recent_orders' => []
                    ],
                    'message' => 'Datos parcialmente disponibles'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error crítico en dashboard:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al cargar el dashboard',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();
            Log::info('Iniciando actualización de perfil para usuario:', ['id' => $user?->id]);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'current_password' => 'required_with:new_password|current_password',
                'new_password' => 'sometimes|min:6|confirmed'
            ]);

            if (isset($validated['new_password'])) {
                $validated['password'] = Hash::make($validated['new_password']);
                unset($validated['new_password']);
            }

            DB::beginTransaction();
            try {
                $user->update($validated);
                DB::commit();

                Log::info('Perfil actualizado exitosamente', ['user_id' => $user->id]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Perfil actualizado correctamente',
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error actualizando perfil:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar perfil',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getProfileDetails()
    {
        try {
            $user = Auth::user();
            Log::info('Obteniendo detalles del perfil:', ['user_id' => $user?->id]);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'created_at' => $user->created_at->format('d/m/Y'),
                        'last_login' => $user->last_login ?? null
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo detalles del perfil:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener detalles del perfil'
            ], 500);
        }
    }

    public function getSettings()
    {
        try {
            $user = Auth::user();
            Log::info('Obteniendo configuración:', ['user_id' => $user?->id]);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Aquí puedes agregar más configuraciones según necesites
            $settings = [
                'notifications' => [
                    'email' => true,
                    'orders' => true,
                    'promotions' => false
                ],
                'preferences' => [
                    'language' => 'es',
                    'currency' => 'PEN'
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'settings' => $settings
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo configuración:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuración'
            ], 500);
        }
    }

    public function updateSettings(Request $request)
    {
        try {
            $user = Auth::user();
            Log::info('Actualizando configuración:', ['user_id' => $user?->id]);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'notifications.email' => 'boolean',
                'notifications.orders' => 'boolean',
                'notifications.promotions' => 'boolean',
                'preferences.language' => 'string|in:es,en',
                'preferences.currency' => 'string|in:PEN,USD'
            ]);

            // Aquí guardas las configuraciones en la base de datos
            // Puedes crear una tabla user_settings si lo necesitas

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración actualizada correctamente',
                'data' => $validated
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando configuración:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuración'
            ], 500);
        }
    }

    public function changePassword(Request $request)
{
    try {
        $request->validate([
            'current_password' => 'required|current_password',
            'new_password' => 'required|min:6|confirmed',
            'new_password_confirmation' => 'required'
        ]);

        $user = Auth::user();
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Contraseña actualizada correctamente'
        ]);
    } catch (\Exception $e) {
        Log::error('Error cambiando contraseña: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}
}
