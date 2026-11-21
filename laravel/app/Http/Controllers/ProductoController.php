<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductoController extends Controller
{
    /**
     * Consulta de productos por categoría y rango de precio.
     * ANTES: trae todas las columnas, cuenta toda la tabla y no usa caché.
     * DESPUÉS (MONITOREO_MEJORAS=true): columnas necesarias, paginación simple y caché de 60 s.
     */
    public function index(Request $request): JsonResponse
    {
        $categoria = $request->query('categoria', 'Electrónica');
        $min = (float) $request->query('min', 0);
        $max = (float) $request->query('max', 10000);

        if (! config('monitoreo.mejoras')) {
            $productos = Producto::where('categoria', $categoria)
                ->whereBetween('precio', [$min, $max])
                ->orderBy('precio')
                ->paginate(20);

            return response()->json($productos);
        }

        $page = max(1, (int) $request->query('page', 1));
        $cacheKey = 'productos:'.md5("{$categoria}|{$min}|{$max}|{$page}");

        $productos = Cache::remember($cacheKey, 60, fn () => Producto::query()
            ->select(['id', 'sku', 'nombre', 'categoria', 'precio', 'existencia'])
            ->where('categoria', $categoria)
            ->whereBetween('precio', [$min, $max])
            ->orderBy('precio')
            ->simplePaginate(20));

        return response()->json($productos);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Producto::findOrFail($id));
    }

    /**
     * Registro de productos.
     * ANTES: sin validación; un campo obligatorio faltante produce un QueryException (500).
     * DESPUÉS: se valida con StoreProductoRequest y se responde 422.
     */
    public function store(Request $request): JsonResponse
    {
        if (config('monitoreo.mejoras')) {
            $datos = app(StoreProductoRequest::class)->validated();
        } else {
            // Sin validar: si falta un campo obligatorio, MySQL rechaza el INSERT (error 500)
            $datos = $request->only(['sku', 'nombre', 'categoria', 'precio', 'existencia', 'descripcion']);
        }

        $producto = Producto::create($datos);
        Log::info('Producto registrado', ['producto_id' => $producto->id, 'sku' => $producto->sku]);

        return response()->json($producto, 201);
    }
}
