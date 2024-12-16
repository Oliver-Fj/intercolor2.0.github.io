<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
/* use Barryvdh\DomPDF\Facade as PDF; */
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserManagementController extends Controller
{
    public function index()
    {
        try {

            Log::info('Intentando obtener usuarios');

            $users = User::select('id', 'name', 'email', 'role', 'status', 'created_at')
                ->orderBy('created_at', 'desc') // Ordenar por fecha de creación
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ?? 'user',
                        'status' => $user->staus ?? 'active',
                        'created_at' => $user->created_at->format('Y-m-d')
                    ];
                });

            Log::info('Usuarios obtenidos exitosamente', ['count' => $users->count()]);

            // Agregar log para debugging
            Log::info('Usuarios obtenidos:', [
                'count' => $users->count(),
                'users' => $users->toArray()
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $users
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error obteniendo usuarios: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios'
            ], 500);
        }
    }

    public function updateStatus(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);

            // Validar que no sea el mismo usuario
            if ($request->user()->id === $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No puedes cambiar tu propio estado'
                ], 400);
            }

            $user->status = $request->status;
            $user->save();

            Log::info('Estado de usuario actualizado', [
                'id' => $userId,
                'status' => $request->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Estado actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando estado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar estado'
            ], 500);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar estado'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Intentando crear usuario:', $request->except('password'));

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'role' => 'required|in:admin,user'
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => 'active'
            ]);

            Log::info('Usuario creado exitosamente:', ['id' => $user->id]);

            // Generar token JWT para el nuevo usuario
            /* $token = JWTAuth::fromUser($user); */

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario creado exitosamente',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => 'active'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación al crear usuario', [
                'errors' => $e->errors()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error de validacion',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear usuario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear usuario'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            Log::info('Intentando eliminar usuario', ['id' => $id]);

            $user = User::findOrFail($id);

            // Prevenir eliminar el propio usuario
            if (auth()->id() === $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No puedes eliminar tu propio usuario'
                ], 400);
            }

            $user->delete();

            Log::info('Usuario eliminado exitosamente', ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error eliminando usuario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar usuario'
            ], 500);
        }
    }

    public function exportExcel()
    {
        return Excel::download(new UsersExport, 'usuarios.xlsx');
    }

    public function exportPdf()
{
    $users = User::active()->get(['name', 'email', 'role', 'created_at', 'status']);

    // Crear vista para el PDF
    $pdf = PDF::loadView('pdf.users', ['users' => $users]);

    // Descargar el PDF
    return $pdf->download('usuarios.pdf');
}
}
