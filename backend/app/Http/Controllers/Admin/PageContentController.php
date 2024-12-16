<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PageContent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PageContentController extends Controller
{
    public function index()
    {
        $contents = PageContent::orderBy('page_name')
            ->orderBy('section_name')
            ->orderBy('order')
            ->get()
            ->groupBy('page_name');

        return response()->json([
            'status' => 'success',
            'data' => $contents
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page_name' => 'required|string',
            'section_name' => 'required|string',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'season' => 'required|string',
            'is_active' => 'boolean',
            'order' => 'integer',
            'additional_data' => 'nullable|json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('page-contents', 'public');
            $data['image_url'] = $path;
        }

        $content = PageContent::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Contenido creado correctamente',
            'data' => $content
        ], 201);
    }

    public function show($id)
    {
        $content = PageContent::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $content
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'page_name' => 'sometimes|required|string',
            'section_name' => 'sometimes|required|string',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'season' => 'sometimes|required|string',
            'is_active' => 'boolean',
            'order' => 'integer',
            'additional_data' => 'nullable|json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $content = PageContent::findOrFail($id);
        $data = $request->except('image');

        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe
            if ($content->image_url) {
                Storage::disk('public')->delete($content->image_url);
            }
            
            $path = $request->file('image')->store('page-contents', 'public');
            $data['image_url'] = $path;
        }

        $content->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Contenido actualizado correctamente',
            'data' => $content
        ]);
    }

    public function destroy($id)
    {
        $content = PageContent::findOrFail($id);

        // Eliminar imagen si existe
        if ($content->image_url) {
            Storage::disk('public')->delete($content->image_url);
        }

        $content->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Contenido eliminado correctamente'
        ]);
    }

    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:page_contents,id',
            'items.*.order' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->items as $item) {
            PageContent::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Orden actualizado correctamente'
        ]);
    }

    public function getByPage($pageName)
    {
        $contents = PageContent::where('page_name', $pageName)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $contents
        ]);
    }

    public function getBySeason($season)
    {
        $contents = PageContent::where('season', $season)
            ->where('is_active', true)
            ->orderBy('page_name')
            ->orderBy('order')
            ->get()
            ->groupBy('page_name');

        return response()->json([
            'status' => 'success',
            'data' => $contents
        ]);
    }
}
