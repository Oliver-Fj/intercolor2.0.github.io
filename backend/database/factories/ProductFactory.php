<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' Paint',
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 500),
            'color' => fake()->colorName(),
            'type' => fake()->randomElement(['Látex', 'Acrílica', 'Esmalte']),
            'image_url' => '/images/' . fake()->word() . '.jpg',
            'featured' => fake()->boolean(),
            'stock' => fake()->numberBetween(0, 1000),
        ];
    }
}
