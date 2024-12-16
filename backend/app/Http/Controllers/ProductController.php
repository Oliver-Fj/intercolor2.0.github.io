<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ImageService;
use Illuminate\Support\Facades\DB;
use App\Exports\ProductsExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductController extends Controller
{
    // Constantes necesarias para los filtros
    const COLORS = [
        'Blanco',
        'Negro',
        'Gris',
        'Beige',
        'Azul',
        'Rojo',
        'Verde',
        'Amarillo'
    ];

    const TYPES = [
        'Interior',
        'Exterior'
    ];

    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    // Método para productos públicos

    public function publicIndex()
    {
        try {
            Log::info('Iniciando publicIndex');
            $products = Product::where('status', 'active')
                ->where('is_public', true)
                ->with('categories')  // Añadido esto
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'category' => $product->type, // Por ahora usamos type
                        'price' => floatval($product->price),
                        'image_url' => $product->image_url,
                        'color' => $product->color,
                        'type' => $product->type,
                        'stock' => intval($product->stock),
                        'status' => $product->status
                    ];
                });

            Log::info('Productos públicos encontrados:', ['count' => $products->count()]);
            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error en publicIndex: ' . $e->getMessage());
            return response()->json(['error' => 'Error al cargar productos'], 500);
        }
    }

    public function getPublicFilters()
    {
        try {
            $categories = Category::all()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name
                ];
            });

            return response()->json([
                'categories' => $categories,
                'colors' => self::COLORS,
                'types' => self::TYPES
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getPublicFilters: ' . $e->getMessage());
            return response()->json(['error' => 'Error al cargar filtros'], 500);
        }
    }

    // Método para mostrar producto público
    public function publicShow($id)
    {
        try {
            $product = Product::where('status', 'active')
                /* ->where('is_public', true) */
                ->findOrFail($id);

            // Obtener productos relacionados
            $relatedProducts = Product::where('status', 'active')
                ->where('id', '!=', $id)
                ->when($product->category_id, function ($query) use ($product) {
                    return $query->where('category_id', $product->category_id);
                })
                ->take(4)
                ->get()
                ->map(function ($relatedProduct) {
                    return [
                        'id' => $relatedProduct->id,
                        'name' => $relatedProduct->name,
                        'description' => $relatedProduct->description,
                        'category' => $relatedProduct->category?->name ?? '',
                        'price' => floatval($relatedProduct->price),
                        'image_url' => $relatedProduct->image_url,
                        'stock' => intval($relatedProduct->stock)
                    ];
                });

            Log::info('Producto encontrado', ['product' => $product]);

            return response()->json([
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'category' => $product->category?->name ?? '',
                'price' => floatval($product->price),
                'image_url' => $product->image_url,
                'stock' => intval($product->stock),
                'relatedProducts' => $relatedProducts
            ]);
        } catch (\Exception $e) {
            Log::error('Error finding public product: ' . $e->getMessage());
            return response()->json([
                'error' => 'Producto no encontrado'
            ], 404);
        }
    }

    // Métodos para el panel de administración
    public function index()
    {
        try {
            Log::info('Iniciando index admin');

            // Primero verificamos si hay productos sin el mapeo
            $rawProducts = Product::all();
            Log::info('Productos raw encontrados:', ['count' => $rawProducts->count()]);

            $products = Product::select(
                'id',
                'name',
                'description',
                'price',
                'stock',
                'status',
                'is_public',
                'image_url',
                'color',
                'type'
            )
                ->with('categories')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($product) {
                    Log::info('Procesando producto:', [
                        'id' => $product->id,
                        'name' => $product->name,
                        'categories' => $product->categories->toArray()
                    ]);

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'category' => $product->type, // Temporalmente usamos type directamente
                        'price' => floatval($product->price),
                        'image_url' => $product->image_url,
                        'stock' => intval($product->stock),
                        'status' => $product->status,
                        'color' => $product->color,
                        'is_public' => $product->is_public,
                        'type' => $product->type,
                        'description' => $product->description
                    ];
                });

            Log::info('Productos procesados:', [
                'count' => $products->count(),
                'first_product' => $products->first()
            ]);

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error en index admin:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error al cargar productos'], 500);
        }
    }

    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product
        ]);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction(); // Solo una vez
            Log::info('=== Iniciando creación de producto ===');
            Log::info('Datos recibidos:', $request->all());

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'color' => 'required|string|max:255',
                'type' => 'required|string|max:255',
                'stock' => 'required|integer|min:0',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_public' => 'boolean',
                'category_id' => 'required|exists:categories,id'
            ]);

            // Procesar imagen
            if ($request->hasFile('image')) {
                try {
                    Log::info('Procesando imagen');
                    $imagePath = $this->imageService->handleProductImage($request->file('image'));
                    $validated['image_url'] = $imagePath;
                    Log::info('Imagen procesada con éxito:', ['path' => $imagePath]);
                } catch (\Exception $e) {
                    Log::error('Error procesando imagen:', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \Exception('Error al procesar la imagen: ' . $e->getMessage());
                }
            }

            // Crear producto
            Log::info('Intentando crear producto con datos:', $validated);
            $product = Product::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'stock' => $validated['stock'],
                'color' => $validated['color'],
                'type' => $validated['type'],
                'is_public' => $request->input('is_public', true),
                'status' => 'active',
                'image_url' => $validated['image_url'] ?? null
            ]);

            // Asociar con la categoría
            if ($request->has('category_id')) {
                Log::info('Asociando categoría:', ['category_id' => $request->category_id]);
                $product->categories()->attach($request->category_id);
            }

            DB::commit();
            Log::info('Producto creado exitosamente:', $product->toArray());

            return response()->json([
                'status' => 'success',
                'message' => 'Producto creado exitosamente',
                'data' => $product
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando producto:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el producto: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Método update modificado para manejar imágenes

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'price' => 'sometimes|required|numeric|min:0',
                'color' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|string|max:255',
                'stock' => 'sometimes|required|integer|min:0',
                'status' => 'sometimes|required|string|in:active,inactive',
                'is_public' => 'sometimes|boolean',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
                'category_id' => 'sometimes|required|exists:categories,id'
            ]);

            if ($request->hasFile('image')) {
                // Eliminar imagen anterior si existe
                if ($product->image_url) {
                    $oldImagePath = str_replace('images/', '', $product->image_url);
                    $this->imageService->deleteProductImage($oldImagePath);
                }

                $imagePath = $this->imageService->handleProductImage($request->file('image'));
                $product->image_url = $imagePath;
            }

            // Actualizar campos del producto
            $product->fill($validated);
            $product->save();

            // Actualizar categoría si se proporciona
            if ($request->has('category_id')) {
                $product->categories()->sync([$request->category_id]);
            }

            DB::commit();

            // Cargar el producto actualizado con sus relaciones
            $product = Product::with('categories')->find($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Producto actualizado correctamente',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando producto:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el producto: ' . $e->getMessage()
            ], 500);
        }
    }

    // Método destroy modificado para eliminar la imagen
    public function destroy($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $product->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting product'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function featured()
    {
        $products = Product::where('featured', true)->take(6)->get();

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    public function search(Request $request)
    {
        try {
            // Logging inicial
            Log::info('=== Iniciando búsqueda de productos ===', [
                'time' => now()->toDateTimeString(),
                'user_id' => auth()->id(),
                'parameters' => $request->all()
            ]);

            $query = Product::query();

            // Logging de la construcción de la consulta
            Log::info('Construyendo consulta de búsqueda');

            // Búsqueda por nombre o descripción
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                Log::info('Aplicando filtro de búsqueda por texto', ['term' => $searchTerm]);

                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Filtros de precio
            if ($request->filled('min_price')) {
                Log::info('Aplicando filtro de precio mínimo', ['min_price' => $request->min_price]);
                $query->where('price', '>=', $request->min_price);
            }

            if ($request->filled('max_price')) {
                Log::info('Aplicando filtro de precio máximo', ['max_price' => $request->max_price]);
                $query->where('price', '<=', $request->max_price);
            }

            // Filtro por color
            if ($request->filled('color')) {
                Log::info('Aplicando filtro de color', ['color' => $request->color]);
                $query->where('color', $request->color);
            }

            // Filtro por tipo
            if ($request->filled('type')) {
                Log::info('Aplicando filtro de tipo', ['type' => $request->type]);
                $query->where('type', $request->type);
            }

            // Filtro de stock
            if ($request->filled('in_stock')) {
                Log::info('Aplicando filtro de stock');
                if ($request->boolean('in_stock')) {
                    $query->where('stock', '>', 0);
                }
            }

            // Ordenamiento
            $sortField = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            Log::info('Aplicando ordenamiento', [
                'field' => $sortField,
                'order' => $sortOrder
            ]);

            $query->orderBy($sortField, $sortOrder);

            // Ejecutar la consulta
            $products = $query->paginate($request->input('per_page', 12));

            Log::info('Búsqueda completada', [
                'total_results' => $products->total(),
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage()
            ]);

            // Obtener filtros disponibles
            $filters = [
                'colors' => Product::distinct()->pluck('color'),
                'types' => Product::distinct()->pluck('type'),
                'price_range' => [
                    'min' => Product::min('price'),
                    'max' => Product::max('price')
                ]
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Búsqueda realizada con éxito',
                'data' => [
                    'products' => $products,
                    'filters' => $filters
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en la búsqueda de productos', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al realizar la búsqueda: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Método para obtener los filtros disponibles
    public function getFilters()
    {
        try {
            $filters = [
                'colors' => Product::distinct()->pluck('color'),
                'types' => Product::distinct()->pluck('type'),
                'price_range' => [
                    'min' => Product::min('price'),
                    'max' => Product::max('price')
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $filters
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo filtros: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener filtros'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function exportExcel()
    {
        try {
            return Excel::download(new ProductsExport, 'productos.xlsx');
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
            $products = Product::all()->map(function ($product) {
                // Si la imagen es una URL completa
                if ($product->image_url && filter_var($product->image_url, FILTER_VALIDATE_URL)) {
                    $imageContent = @file_get_contents($product->image_url);
                    if ($imageContent) {
                        $product->image_base64 = "data:image/jpeg;base64," . base64_encode($imageContent);
                    }
                }
                // Si la imagen está en storage
                else if ($product->image_url) {
                    $imagePath = storage_path('app/public/' . $product->image_url);
                    if (file_exists($imagePath)) {
                        $imageContent = file_get_contents($imagePath);
                        $product->image_base64 = "data:image/jpeg;base64," . base64_encode($imageContent);
                    }
                }

                if (empty($product->image_base64)) {
                    // Crear una imagen de placeholder con texto
                    $img = imagecreatetruecolor(50, 50);
                    $bgColor = imagecolorallocate($img, 240, 240, 240);
                    $textColor = imagecolorallocate($img, 150, 150, 150);
                    imagefill($img, 0, 0, $bgColor);
                    imagestring($img, 2, 5, 20, 'No image', $textColor);

                    ob_start();
                    imagejpeg($img);
                    $imageData = ob_get_clean();
                    imagedestroy($img);

                    $product->image_base64 = "data:image/jpeg;base64," . base64_encode($imageData);
                }

                return $product;
            });

            $pdf = PDF::loadView('pdf.products', [
                'products' => $products,
                'currentDateTime' => now()->format('d/m/Y H:i:s'),
                'userName' => auth()->user()->name
            ]);

            $pdf->setPaper('A4');
            return $pdf->download('productos-' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error exportando PDF: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al exportar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
