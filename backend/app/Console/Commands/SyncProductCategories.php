<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Category;

class SyncProductCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:sync-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza las categorías de los productos basados en su tipo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de categorías...');

        $products = Product::all();
        $count = 0;

        foreach ($products as $product) {
            if ($product->type) {
                $category = Category::where('name', 'like', "%{$product->type}%")->first();
                if ($category) {
                    $product->categories()->sync([$category->id]);
                    $count++;
                }
            }
        }

        $this->info("Sincronización completada. {$count} productos actualizados.");
    }
}
