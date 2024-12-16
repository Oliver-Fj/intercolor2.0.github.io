<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderManagementController;
use App\Http\Controllers\Admin\StockManagementController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PageContentController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\SliderGroupController;
use App\Http\Controllers\Admin\CategoryController;



/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
*/

// Autenticación pública
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Rutas públicas de productos
Route::prefix('products')->group(function () {
    Route::get('/public', [ProductController::class, 'publicIndex']);
    Route::get('/public/filters', [ProductController::class, 'getPublicFilters']);
    Route::get('/public/{id}', [ProductController::class, 'publicShow']);
});

// Ruta pública de sliders
Route::get('/sliders/active', [SliderController::class, 'getActiveSliders']);

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (Requieren Autenticación)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Rutas de búsqueda (deben ir primero)
    Route::get('/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/filters', [ProductController::class, 'getFilters'])->name('products.filters');

    // Autenticación
    Route::prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Panel de Usuario 
    Route::prefix('user')->group(function () {
        Route::get('/dashboard', [UserController::class, 'dashboard']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::get('/profile/details', [UserController::class, 'getProfileDetails']);
        Route::get('/settings', [UserController::class, 'getSettings']);
        Route::put('/settings', [UserController::class, 'updateSettings']);
        Route::put('/password', [UserController::class, 'changePassword']);
    });

    // Dashboard de Administrador
    Route::middleware('admin')->prefix('admin/dashboard')->group(function () {
        Route::get('/statistics', [DashboardController::class, 'statistics']);
        Route::get('/revenue', [DashboardController::class, 'revenueByPeriod']);
    });

    // Reportes de Administrador
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/reports')->group(function () {
        Route::get('/sales', [DashboardController::class, 'exportSalesReport']);
        Route::get('/inventory', [DashboardController::class, 'exportInventoryReport']);
    });

    // Gestión de Ordenes
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/orders')->group(function () {
        Route::get('/', [OrderManagementController::class, 'index']);
        Route::put('/{orderId}/status', [OrderManagementController::class, 'updateStatus']);
        Route::get('/{orderId}/history', [OrderManagementController::class, 'getStatusHistory']);
        Route::get('/stats', [OrderManagementController::class, 'getOrderStats']);
    });

    // Gestión de Stock
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/stock')->group(function () {
        Route::post('products/{id}/adjust', [StockManagementController::class, 'adjustStock']);
        Route::get('history', [StockManagementController::class, 'getStockHistory']);
        Route::get('products/{id}/history', [StockManagementController::class, 'getStockHistory']);
        Route::post('products/{id}/alert', [StockManagementController::class, 'setStockAlert']);
        Route::get('low-stock', [StockManagementController::class, 'getLowStockProducts']);
    });

    // Gestión de Usuarios y Contenido
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        // Gestión de Usuarios
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
        Route::put('/users/{id}/status', [UserManagementController::class, 'updateStatus']);
        Route::get('/users/export/excel', [App\Http\Controllers\Admin\UserController::class, 'exportExcel']);
        Route::get('/users/export/pdf', [App\Http\Controllers\Admin\UserController::class, 'exportPDF']);

        // Gestión de Contenido de Página
        Route::get('page-contents/page/{pageName}', [PageContentController::class, 'getByPage']);
        Route::get('page-contents/season/{season}', [PageContentController::class, 'getBySeason']);
        Route::post('page-contents/order', [PageContentController::class, 'updateOrder']);
        Route::resource('page-contents', PageContentController::class);

        // Gestión de Sliders
        Route::get('/sliders', [SliderController::class, 'index']);
        Route::post('/sliders', [SliderController::class, 'store']);
        Route::delete('/sliders/{id}', [SliderController::class, 'destroy']);
        Route::post('/sliders/order', [SliderController::class, 'updateOrder']);
        Route::post('/sliders/publish', [SliderController::class, 'publish']);
        Route::get('/slider-groups', [SliderGroupController::class, 'index']);
        Route::post('/slider-groups', [SliderGroupController::class, 'store']);
        Route::put('/slider-groups/{id}', [SliderGroupController::class, 'update']);
        // routes/api.php
        Route::put('/slider-groups/{id}/publish', [SliderGroupController::class, 'publish']);
        Route::delete('/slider-groups/{id}', [SliderGroupController::class, 'destroy']);
        Route::post('/slider-groups/{id}/sliders', [SliderGroupController::class, 'addSliders']);
        Route::delete('/slider-groups/{id}/sliders/{sliderId}', [SliderGroupController::class, 'removeSlider']);
        // Ruta para sliders no asignados
        Route::get('/sliders/unassigned', [SliderController::class, 'getUnassigned']);

        // rutas de categorias
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        Route::get('/categories/{id}/products', [CategoryController::class, 'getProducts']);
    });

    // Productos
    Route::prefix('products')->group(function () {
        // Rutas específicas primero
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/filters', [ProductController::class, 'getFilters']);
        Route::get('/featured', [ProductController::class, 'featured']);

        // Rutas CRUD después
        Route::get('/', [ProductController::class, 'index']);

        // Rutas de administrador
        Route::middleware('admin')->group(function () {
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']);
            Route::get('/export/excel', [ProductController::class, 'exportExcel']); 
            Route::get('/export/pdf', [ProductController::class, 'exportPDF']);
        });

        // Ruta con parámetro al final
        Route::get('/{id}', [ProductController::class, 'show']);
    });

    // Carrito
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add', [CartController::class, 'addToCart']);
        Route::put('/items/{cartItemId}', [CartController::class, 'updateQuantity']);
        Route::delete('/items/{cartItemId}', [CartController::class, 'removeFromCart']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
    });

    // Órdenes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
    });
});
