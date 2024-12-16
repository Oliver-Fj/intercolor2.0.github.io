<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    protected $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function handleProductImage(UploadedFile $image)
    {
        try {
            Log::info('Iniciando procesamiento de imagen');

            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $frontendPath = base_path('../frontend/public/images');

            if (!file_exists($frontendPath)) {
                mkdir($frontendPath, 0777, true);
            }

            $img = $this->manager->read($image->getRealPath());
            $img->scale(width: 800);
            $img->save($frontendPath . '/' . $filename, quality: 80);

            return 'images/' . $filename;
        } catch (\Exception $e) {
            Log::error('Error procesando imagen:', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    public function deleteProductImage($imagePath)
    {
        try {
            if (Storage::exists('public/' . $imagePath)) {
                Storage::delete('public/' . $imagePath);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Error eliminando imagen: ' . $e->getMessage());
            throw $e;
        }
    }
}
