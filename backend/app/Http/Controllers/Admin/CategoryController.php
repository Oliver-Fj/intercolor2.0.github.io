<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CategoryController extends Controller
{
    public function index()
    {
        try {
            $categories = Category::withCount('products')  // Simplificado a solo contar productos
                ->with(['parent', 'children'])
                ->orderBy('order')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'parent_id' => $category->parent_id,
                        'order' => $category->order,
                        'active' => $category->active,
                        'products_count' => $category->products_count,
                        'children' => $category->children
                    ];
                });

            return response()->json($categories);
        } catch (\Exception $e) {
            Log::error('Error en CategoryController@index: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Intento de crear categoría:', $request->all());

            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:categories,id',
                'order' => 'integer|nullable',
                'active' => 'boolean|nullable'
            ]);

            DB::beginTransaction();

            $category = Category::create([
                'name' => $request->name,
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'order' => $request->order ?? 0,
                'active' => $request->active ?? true
            ]);

            DB::commit();

            Log::info('Categoría creada exitosamente:', $category->toArray());

            return response()->json($category, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear categoría:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'details' => 'Error al crear la categoría'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($id) {
                    if ($value == $id) {
                        $fail('Una categoría no puede ser su propio padre.');
                    }
                }
            ],
            'active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $category = Category::findOrFail($id);

            // Verificar que no se cree un ciclo en la jerarquía
            if ($request->parent_id) {
                $parent = Category::find($request->parent_id);
                if ($parent && $parent->getAllChildren()->contains($id)) {
                    throw new \Exception('No se puede crear un ciclo en la jerarquía de categorías.');
                }
            }

            $category->update($request->all());

            DB::commit();

            return response()->json($category);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $category = Category::findOrFail($id);

            // Mover los productos a la categoría padre si existe
            if ($category->parent_id) {
                $category->products()->update(['category_id' => $category->parent_id]);
            }

            // Mover las subcategorías al padre
            $category->children()->update(['parent_id' => $category->parent_id]);

            $category->delete();

            DB::commit();

            return response()->json(['message' => 'Categoría eliminada correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:categories,id',
            'orders.*.order' => 'required|integer|min:0'
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->orders as $item) {
                Category::where('id', $item['id'])
                    ->update(['order' => $item['order']]);
            }

            DB::commit();

            return response()->json(['message' => 'Orden actualizado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->active = !$category->active;
            $category->save();

            return response()->json([
                'message' => 'Estado actualizado correctamente',
                'active' => $category->active
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProducts($id)
    {
        try {
            Log::info('Obteniendo productos de categoría:', ['category_id' => $id]);

            $category = Category::findOrFail($id);
            $products = $category->products()
                ->with('categories')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => floatval($product->price),
                        'stock' => intval($product->stock),
                        'image_url' => $product->image_url,
                        'type' => $product->type,
                        'color' => $product->color
                    ];
                });

            Log::info('Productos encontrados:', ['count' => $products->count()]);
            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error obteniendo productos:', [
                'category_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
