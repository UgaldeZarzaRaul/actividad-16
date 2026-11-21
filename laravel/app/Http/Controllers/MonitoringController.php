<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequestMonitoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MonitoringController extends Controller
{
    /**
     * Estado de salud de la aplicación y de la base de datos.
     * Devuelve 200 si todo funciona y 503 si MySQL no responde.
     */
    public function health(): JsonResponse
    {
        $database = $this->checkDatabase();
        $healthy = $database['status'] === 'up';

        return response()->json([
            'status' => $healthy ? 'up' : 'down',
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'database' => $database,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /**
     * Métricas en formato de texto de Prometheus.
     */
    public function metrics(): Response
    {
        $database = $this->checkDatabase();
        $logFile = storage_path('logs/laravel.log');
        $lines = [];

        $lines[] = '# HELP laravel_up La aplicación Laravel responde.';
        $lines[] = '# TYPE laravel_up gauge';
        $lines[] = 'laravel_up 1';

        $lines[] = '# HELP laravel_info Versiones de Laravel y PHP.';
        $lines[] = '# TYPE laravel_info gauge';
        $lines[] = sprintf('laravel_info{laravel="%s",php="%s",env="%s"} 1',
            app()->version(), PHP_VERSION, app()->environment());

        $lines[] = '# HELP laravel_http_requests_total Peticiones HTTP atendidas por clase de código.';
        $lines[] = '# TYPE laravel_http_requests_total counter';
        foreach (['2xx', '3xx', '4xx', '5xx'] as $class) {
            $lines[] = sprintf('laravel_http_requests_total{status="%s"} %d',
                $class, (int) Cache::get("monitoring:requests:{$class}", 0));
        }

        $lines[] = '# HELP laravel_http_request_duration_seconds Duración de las peticiones HTTP.';
        $lines[] = '# TYPE laravel_http_request_duration_seconds summary';
        $lines[] = sprintf('laravel_http_request_duration_seconds_sum %.6f',
            ((int) Cache::get('monitoring:duration:sum_us', 0)) / 1_000_000);
        $lines[] = sprintf('laravel_http_request_duration_seconds_count %d',
            (int) Cache::get('monitoring:duration:count', 0));

        $lines[] = '# HELP laravel_http_request_duration_hist_seconds Histograma de duración de peticiones.';
        $lines[] = '# TYPE laravel_http_request_duration_hist_seconds histogram';
        foreach (RequestMonitoring::BUCKETS as $le) {
            $lines[] = sprintf('laravel_http_request_duration_hist_seconds_bucket{le="%s"} %d',
                $le, (int) Cache::get("monitoring:bucket:{$le}", 0));
        }
        $lines[] = sprintf('laravel_http_request_duration_hist_seconds_bucket{le="+Inf"} %d',
            (int) Cache::get('monitoring:duration:count', 0));
        $lines[] = sprintf('laravel_http_request_duration_hist_seconds_sum %.6f',
            ((int) Cache::get('monitoring:duration:sum_us', 0)) / 1_000_000);
        $lines[] = sprintf('laravel_http_request_duration_hist_seconds_count %d',
            (int) Cache::get('monitoring:duration:count', 0));

        $lines[] = '# HELP laravel_http_slow_requests_total Peticiones que superan 2 segundos.';
        $lines[] = '# TYPE laravel_http_slow_requests_total counter';
        $lines[] = 'laravel_http_slow_requests_total '.(int) Cache::get('monitoring:slow:count', 0);

        $lines[] = '# HELP laravel_route_requests_total Peticiones atendidas por ruta.';
        $lines[] = '# TYPE laravel_route_requests_total counter';
        $routeLines = ['sum' => [], 'errors' => []];
        foreach (Cache::get('monitoring:routes', []) as $key => $route) {
            $label = str_replace(['\\', '"'], ['\\\\', '\\"'], $route);
            $lines[] = sprintf('laravel_route_requests_total{route="%s"} %d',
                $label, (int) Cache::get("monitoring:route:{$key}:count", 0));
            $routeLines['sum'][] = sprintf('laravel_route_duration_seconds_sum{route="%s"} %.6f',
                $label, ((int) Cache::get("monitoring:route:{$key}:sum_us", 0)) / 1_000_000);
            $routeLines['errors'][] = sprintf('laravel_route_errors_total{route="%s"} %d',
                $label, (int) Cache::get("monitoring:route:{$key}:errors", 0));
        }
        $lines[] = '# HELP laravel_route_duration_seconds_sum Tiempo acumulado por ruta.';
        $lines[] = '# TYPE laravel_route_duration_seconds_sum counter';
        array_push($lines, ...$routeLines['sum']);
        $lines[] = '# HELP laravel_route_errors_total Respuestas 5xx por ruta.';
        $lines[] = '# TYPE laravel_route_errors_total counter';
        array_push($lines, ...$routeLines['errors']);

        $lines[] = '# HELP laravel_php_memory_peak_bytes Memoria máxima usada por PHP en la última petición.';
        $lines[] = '# TYPE laravel_php_memory_peak_bytes gauge';
        $lines[] = 'laravel_php_memory_peak_bytes '.(int) Cache::get('monitoring:memory_peak_bytes', 0);

        $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
        $lines[] = '# HELP laravel_system_load1 Carga promedio del sistema en el último minuto (uso de CPU).';
        $lines[] = '# TYPE laravel_system_load1 gauge';
        $lines[] = sprintf('laravel_system_load1 %.2f', $load[0]);

        $lines[] = '# HELP laravel_db_up La base de datos responde a la aplicación.';
        $lines[] = '# TYPE laravel_db_up gauge';
        $lines[] = 'laravel_db_up '.($database['status'] === 'up' ? 1 : 0);

        $lines[] = '# HELP laravel_db_response_seconds Tiempo de respuesta de una consulta de prueba.';
        $lines[] = '# TYPE laravel_db_response_seconds gauge';
        $lines[] = sprintf('laravel_db_response_seconds %.6f', $database['response_ms'] / 1000);

        $lines[] = '# HELP laravel_log_file_bytes Tamaño del archivo laravel.log.';
        $lines[] = '# TYPE laravel_log_file_bytes gauge';
        $lines[] = 'laravel_log_file_bytes '.(is_file($logFile) ? filesize($logFile) : 0);

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    /**
     * Operación de consulta instrumentada con trazas: cada paso registra su
     * duración en el log "monitoring" con el mismo request_id.
     */
    public function traceQuery(): JsonResponse
    {
        $requestId = request()->attributes->get('request_id');
        $trace = [];

        $result = $this->span('db.listar_tablas', $trace, fn () => collect(DB::select('SHOW TABLES'))->count());
        $users = $this->span('db.contar_usuarios', $trace, function () {
            try {
                return DB::table('users')->count();
            } catch (Throwable) {
                return null;
            }
        });
        $this->span('app.preparar_respuesta', $trace, fn () => usleep(20_000));

        Log::channel('monitoring')->info('trace_completed', [
            'request_id' => $requestId,
            'operation' => 'GET /api/monitoreo/consulta',
            'spans' => $trace,
            'total_ms' => round(array_sum(array_column($trace, 'duration_ms')), 2),
        ]);

        return response()->json([
            'request_id' => $requestId,
            'tablas' => $result,
            'usuarios' => $users,
            'traza' => $trace,
        ]);
    }

    /**
     * Provoca un error controlado (solo fuera de producción) para comprobar
     * que se registra en laravel.log, en Telescope y en las métricas 5xx.
     */
    public function testError(): never
    {
        abort_if(app()->isProduction(), 404);

        throw new RuntimeException('Error controlado de prueba para el monitoreo');
    }

    private function checkDatabase(): array
    {
        $start = hrtime(true);

        try {
            DB::select('SELECT 1');

            return [
                'status' => 'up',
                'connection' => config('database.default'),
                'response_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ];
        } catch (Throwable $e) {
            Log::error('Fallo en el health check de base de datos', ['error' => $e->getMessage()]);

            return [
                'status' => 'down',
                'connection' => config('database.default'),
                'response_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ];
        }
    }

    private function span(string $name, array &$trace, callable $callback): mixed
    {
        $start = hrtime(true);
        $value = $callback();
        $duration = round((hrtime(true) - $start) / 1_000_000, 2);

        $trace[] = ['span' => $name, 'duration_ms' => $duration];
        Log::channel('monitoring')->debug('span', ['span' => $name, 'duration_ms' => $duration]);

        return $value;
    }
}
