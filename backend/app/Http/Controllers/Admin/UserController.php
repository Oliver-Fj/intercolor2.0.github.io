<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::select('id', 'name', 'email', 'role', 'status', 'created_at')
                ->latest()
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo usuarios: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener usuarios'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'role' => 'required|in:admin,user'
            ]);

            $userId = DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $user = DB::table('users')->where('id', $userId)->first();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario creado exitosamente',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear usuario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:0,1'
            ]);

            $user = User::findOrFail($id);
            $user->status = (int)$request->status;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Estado actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar estado'
            ], 500);
        }
    }

    public function exportExcel()
    {
        try {
            return Excel::download(new UsersExport, 'usuarios.xlsx');
        } catch (\Exception $e) {
            Log::error('Error exportando Excel: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al exportar el archivo'
            ], 500);
        }
    }

    public function exportPDF()
    {
        try {
            $users = User::select('name', 'email', 'role', 'status', 'created_at')
                ->get()
                ->map(function ($user) {
                    return [
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role === 'admin' ? 'Administrador' : 'Usuario',
                        'status' => $user->status === 1 ? 'Activo' : 'Inactivo',
                        'created_at' => $user->created_at->format('d/m/Y')
                    ];
                });

            $pdf = PDF::loadView('pdf.users', compact('users'));
            return $pdf->download('usuarios.pdf');
        } catch (\Exception $e) {
            Log::error('Error exportando PDF: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al exportar PDF'
            ], 500);
        }
    }
}
