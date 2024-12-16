<?php

namespace Database\Seeders;

//use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Pintura Blanca Premium',
                'description' => 'Pintura látex de alta calidad para interiores',
                'price' => 299.99,
                'color' => 'Blanco',
                'type' => 'Látex',
                'image_url' => '/images/white-paint.jpg',
                'featured' => true,
                'stock' => 100
            ],
            [
                'name' => 'Pintura Azul Marina',
                'description' => 'Pintura acrílica resistente al agua',
                'price' => 349.99,
                'color' => 'Azul',
                'type' => 'Acrílica',
                'image_url' => '/images/blue-paint.jpg',
                'featured' => false,
                'stock' => 75
            ],
            // Puedes agregar más productos aquí
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
