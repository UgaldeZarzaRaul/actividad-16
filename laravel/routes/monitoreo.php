<?php

use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

// Agregar al final de routes/web.php:  require __DIR__.'/monitoreo.php';

// Monitoreo
Route::get('/api/health', [MonitoringController::class, 'health']);
Route::get('/metrics', [MonitoringController::class, 'metrics']);
Route::get('/api/monitoreo/consulta', [MonitoringController::class, 'traceQuery']);
Route::get('/monitoreo/error-prueba', [MonitoringController::class, 'testError']);

// Caso de estudio: productos (sin CSRF para poder probar con JMeter y curl)
Route::withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
])
    ->prefix('api/productos')
    ->group(function () {
        Route::get('/', [ProductoController::class, 'index']);
        Route::get('/{id}', [ProductoController::class, 'show'])->whereNumber('id');
        Route::post('/', [ProductoController::class, 'store']);
    });
