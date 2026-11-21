<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Instrumenta cada petición HTTP:
 *  - Asigna un identificador de traza (X-Request-Id) que acompaña a todos los logs.
 *  - Mide la duración total y la publica en el encabezado Server-Timing.
 *  - Acumula métricas para Prometheus: peticiones por código, histograma de duración,
 *    peticiones lentas (> 2 s), duración por ruta y memoria máxima de PHP.
 *  - Escribe un registro estructurado en el canal "monitoring".
 */
class RequestMonitoring
{
    public const BUCKETS = [0.1, 0.25, 0.5, 1, 2, 5];

    public const SLOW_THRESHOLD_SECONDS = 2.0;

    private const EXCLUDED_PATHS = ['metrics', 'telescope*', 'favicon.ico', 'vendor/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $start = hrtime(true);
        $response = $next($request);
        $duration = (hrtime(true) - $start) / 1_000_000_000;

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('Server-Timing', sprintf('app;dur=%.2f', $duration * 1000));

        if ($request->is(...self::EXCLUDED_PATHS)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $route = $request->method().' /'.ltrim($request->route()?->uri() ?? 'sin-ruta', '/');

        $this->recordMetrics($status, $duration, $route);

        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $route,
            'status' => $status,
            'duration_ms' => round($duration * 1000, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1_048_576, 2),
            'ip' => $request->ip(),
        ];

        if ($duration > self::SLOW_THRESHOLD_SECONDS) {
            Log::channel('monitoring')->warning('slow_request', $context);
        } elseif ($status >= 500) {
            Log::channel('monitoring')->error('http_request', $context);
        } else {
            Log::channel('monitoring')->info('http_request', $context);
        }

        return $response;
    }

    private function recordMetrics(int $status, float $duration, string $route): void
    {
        $this->increment('monitoring:requests:'.intdiv($status, 100).'xx');
        $this->increment('monitoring:duration:count');
        $this->increment('monitoring:duration:sum_us', (int) round($duration * 1_000_000));

        foreach (self::BUCKETS as $le) {
            if ($duration <= $le) {
                $this->increment("monitoring:bucket:{$le}");
            }
        }

        if ($duration > self::SLOW_THRESHOLD_SECONDS) {
            $this->increment('monitoring:slow:count');
        }

        $routeKey = md5($route);
        $routes = Cache::get('monitoring:routes', []);
        if (! isset($routes[$routeKey])) {
            $routes[$routeKey] = $route;
            Cache::forever('monitoring:routes', $routes);
        }
        $this->increment("monitoring:route:{$routeKey}:count");
        $this->increment("monitoring:route:{$routeKey}:sum_us", (int) round($duration * 1_000_000));
        if ($status >= 500) {
            $this->increment("monitoring:route:{$routeKey}:errors");
        }

        Cache::forever('monitoring:memory_peak_bytes', memory_get_peak_usage(true));
    }

    private function increment(string $key, int $value = 1): void
    {
        Cache::add($key, 0);
        Cache::increment($key, $value);
    }
}
