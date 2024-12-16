<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SliderGroup;
use App\Models\Slider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class SliderGroupController extends Controller
{
    public function index()
    {
        $groups = SliderGroup::with('sliders')
            ->orderBy('order')
            ->get();

        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7'
        ]);

        $group = SliderGroup::create([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color ?? '#00A0DF',
            'order' => SliderGroup::max('order') + 1
        ]);

        return response()->json($group, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7'
        ]);

        $group = SliderGroup::findOrFail($id);
        $group->update($request->all());

        return response()->json($group);
    }

    public function destroy($id)
    {
        $group = SliderGroup::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Grupo eliminado correctamente']);
    }

    public function publish($id)
    {
        try {
            $group = SliderGroup::findOrFail($id);
            // Desactivar todos los sliders individuales y otros grupos
            Slider::query()->update(['active' => false]);
            SliderGroup::where('id', '!=', $id)->update(['active' => false]);

            // Activar el grupo seleccionado y sus sliders
            $group->update(['active' => true]);
            $group->sliders()->update(['active' => true]);

            return response()->json([
                'message' => 'Grupo publicado correctamente',
                'active' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al publicar grupo:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function addSliders(Request $request, $id)
    {
        Log::info('Request recibido:', $request->all());

        try {
            $group = SliderGroup::findOrFail($id);
            $group->sliders()->attach($request->sliderIds);
            return response()->json(['message' => 'Sliders agregados correctamente']);
        } catch (\Exception $e) {
            Log::error('Error:', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function removeSlider($groupId, $sliderId)
    {
        $group = SliderGroup::findOrFail($groupId);
        $group->sliders()->detach($sliderId);

        // Reordenar los sliders restantes
        $group->sliders()
            ->get()
            ->each(function ($slider, $index) use ($group) {
                $group->sliders()
                    ->updateExistingPivot($slider->id, ['order' => $index + 1]);
            });

        return response()->json(['message' => 'Slider removido correctamente']);
    }

    public function getUnassigned()
    {
        // Obtener sliders que no están en ningún grupo
        $unassignedSliders = Slider::whereDoesntHave('groups')
            ->orWhereHas('groups', function ($query) {
                $query->where('active', false);
            })
            ->get();

        return response()->json($unassignedSliders);
    }
}
