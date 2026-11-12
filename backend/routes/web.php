<?php

use App\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', [DocsController::class, 'ui']);
Route::get('/docs/openapi.yaml', [DocsController::class, 'openapi']);
Route::get('/docs/assets/{path}', [DocsController::class, 'asset'])
    ->where('path', '.*');

// Imágenes subidas (disco "public"). Si existe el enlace public/storage (php artisan storage:link)
// el servidor web entrega el archivo directamente; si no existe (por ejemplo en Windows sin
// permiso para crear enlaces simbólicos) esta ruta sirve el archivo y las imágenes no se rompen.
Route::get('/storage/{path}', function (string $path) {
    $disk = Illuminate\Support\Facades\Storage::disk('public');

    try {
        if (str_contains($path, '..') || ! $disk->exists($path)) {
            abort(404);
        }
    } catch (League\Flysystem\PathTraversalDetected) {
        abort(404);
    }

    return response()->file($disk->path($path), [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');
