<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Suport\Facades\DB;
use Illuminate\Support\Facades\Log;


class SliderController extends Controller
{
    public function index()
    {
        $sliders = Slider::orderBy('order')->get();
        return response()->json($sliders);
    }

    // app/Http/Controllers/Admin/SliderController.php

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();

            // Mover la imagen al directorio del frontend
            $file->move(public_path('../../frontend/public/images/sliders'), $filename);

            $slider = Slider::create([
                'title' => $request->title,
                'image_url' => '/images/sliders/' . $filename,
                'order' => Slider::max('order') + 1,
                'active' => false
            ]);

            return response()->json($slider, 201);
        }

        return response()->json(['message' => 'No se pudo subir la imagen'], 400);
    }

    public function destroy($id)
    {
        $slider = Slider::findOrFail($id);

        // Eliminar la imagen del storage
        $path = str_replace('/storage/', '', $slider->image_url);
        Storage::disk('public')->delete($path);

        $slider->delete();

        return response()->json(['message' => 'Slider eliminado correctamente']);
    }

    // app/Http/Controllers/Admin/SliderController.php
    public function getActiveSliders()
    {
        try {
            // Obtener sliders de grupos activos
            $groupSliders = Slider::whereHas('groups', function ($query) {
                $query->where('active', true);
            })->get();

            // Obtener sliders individuales activos
            $individualSliders = Slider::whereDoesntHave('groups')
                ->where('active', true)
                ->get();

            // Combinar ambos conjuntos
            $allActiveSliders = $groupSliders->merge($individualSliders);

            return response()->json($allActiveSliders);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function publish(Request $request)
    {
        $request->validate([
            'sliderIds' => 'required|array',
            'sliderIds.*' => 'exists:sliders,id'
        ]);

        try {
            // Desactivar todos los sliders primero
            Slider::query()->update(['active' => false]);

            // Activar solo los seleccionados
            Slider::whereIn('id', $request->sliderIds)
                ->update(['active' => true]);

            return response()->json([
                'message' => 'Sliders publicados correctamente',
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al publicar los sliders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnassigned()
    {
        try {
            $unassignedSliders = Slider::whereDoesntHave('groups')
                ->get();

            Log::info('Sliders no asignados:', ['count' => $unassignedSliders->count()]);

            return response()->json($unassignedSliders);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:sliders,id',
            'orders.*.order' => 'required|integer|min:0'
        ]);

        foreach ($request->orders as $item) {
            Slider::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }
}
