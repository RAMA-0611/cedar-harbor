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
