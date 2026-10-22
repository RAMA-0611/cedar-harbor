<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::get('products', [CatalogController::class, 'index']);
Route::get('products/{productId}', [CatalogController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:ADMIN'])->prefix('admin')->group(function () {
    Route::apiResource('categories', AdminCategoryController::class);
    Route::apiResource('products', AdminProductController::class);
    Route::apiResource('customers', AdminCustomerController::class);
    Route::apiResource('users', AdminUserController::class);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{orderId}', [AdminOrderController::class, 'show']);
    Route::patch('orders/{orderId}', [AdminOrderController::class, 'update']);
    Route::delete('orders/{orderId}', [AdminOrderController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:CUSTOMER'])->group(function () {
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'storeItem']);
    Route::patch('cart/items/{cartItemId}', [CartController::class, 'updateItem']);
    Route::delete('cart/items/{cartItemId}', [CartController::class, 'destroyItem']);

    Route::post('checkout/validate', [CheckoutController::class, 'validateCheckout']);

    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{orderId}', [OrderController::class, 'show']);
    Route::get('orders/{orderId}/status', [OrderController::class, 'status']);
    Route::post('orders/{orderId}/cancel', [OrderController::class, 'cancel']);
});
