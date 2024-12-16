<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    const MAX_ATTEMPTS = 5;
    const LOCK_TIME = 30; // minutos

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buscar usuario por email primero
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Credenciales inválidas'
                ], 401);
            }

            // Verificar bloqueo
            if ($user->locked_until && now()->lt($user->locked_until)) {
                $minutesLeft = ceil(now()->diffInSeconds($user->locked_until) / 60);
                return response()->json([
                    'status' => 'error',
                    'message' => "Cuenta bloqueada. Intente nuevamente en {$minutesLeft} minutos.",
                    'locked_until' => $user->locked_until->timestamp,
                    'is_locked' => true,
                    'minutes_left' => $minutesLeft
                ], 403);
            }

            // Si ya pasó el tiempo de bloqueo, resetear los intentos
            if ($user->locked_until && now()->gt($user->locked_until)) {
                $user->login_attempts = 0;
                $user->locked_until = null;
                $user->save();
            }

            // Verificar credenciales
            if (!Auth::attempt($request->only('email', 'password'))) {
                $user->login_attempts += 1;
                $user->last_failed_attempt = now();

                // Si alcanzó 5 intentos, bloquear
                if ($user->login_attempts >= 5) {
                    $user->locked_until = now()->addMinutes(30);
                    $user->save();

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cuenta bloqueada por múltiples intentos fallidos. Intente nuevamente en 30 minutos.',
                        'locked_until' => $user->locked_until->timestamp,
                        'is_locked' => true,
                        'minutes_left' => 30
                    ], 403);
                }

                $user->save();

                $remainingAttempts = 5 - $user->login_attempts;
                return response()->json([
                    'status' => 'error',
                    'message' => "Credenciales inválidas. Le quedan {$remainingAttempts} intentos.",
                    'remaining_attempts' => $remainingAttempts
                ], 401);
            }

            // Login exitoso - resetear todo
            $user->login_attempts = 0;
            $user->locked_until = null;
            $user->last_failed_attempt = null;
            $user->save();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Sesión iniciada correctamente',
                'user' => $user,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error durante el inicio de sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|unique:users|max:255',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => explode('@', $request->email)[0], // Nombre temporal basado en el email
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user'
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario creado exitosamente',
                'user' => $user,
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user' => $request->user()
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Sesión cerrada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cerrar sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
