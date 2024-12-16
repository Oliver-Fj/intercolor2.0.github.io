<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpia la tabla pivote
        DB::table('category_product')->truncate();

        // Obtiene todos los productos
        $products = Product::all();

        foreach ($products as $product) {
            // Busca la categoría basada en el tipo del producto
            $category = Category::where('name', 'like', "%{$product->type}%")->first();

            if ($category) {
                // Crea la relación en la tabla pivote
                DB::table('category_product')->insert([
                    'category_id' => $category->id,
                    'product_id' => $product->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
}
