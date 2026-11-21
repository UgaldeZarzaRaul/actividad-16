#!/bin/zsh
# Fase 3. Ejecuta los escenarios 1, 3 y 4 (el 2 se corre con JMeter y el 5 con disponibilidad.sh)
APP=${APP_URL:-http://localhost:8000}
H=(-H "Accept: application/json" -H "Content-Type: application/json")

echo "== Escenario 1. Operación normal"
curl -s -o /dev/null -w "GET /                        -> %{http_code} en %{time_total}s\n" "$APP/"
curl -s -o /dev/null -w "GET /api/productos (consulta) -> %{http_code} en %{time_total}s\n" "${H[@]}" "$APP/api/productos?categoria=Hogar&min=100&max=5000"
curl -s -o /dev/null -w "GET /api/productos/1         -> %{http_code} en %{time_total}s\n" "${H[@]}" "$APP/api/productos/1"
curl -s -o /dev/null -w "POST /api/productos (registro)-> %{http_code} en %{time_total}s\n" "${H[@]}" \
  -d "{\"sku\":\"ESC1-$(date +%s)\",\"nombre\":\"Producto de prueba\",\"categoria\":\"Hogar\",\"precio\":199.90,\"existencia\":5}" "$APP/api/productos"

echo; echo "== Escenario 3. Error controlado"
curl -s -o /dev/null -w "GET /monitoreo/error-prueba          -> %{http_code}\n" "$APP/monitoreo/error-prueba"
curl -s -o /dev/null -w "POST /api/productos sin nombre       -> %{http_code}\n" "${H[@]}" \
  -d '{"sku":"ESC3-INCOMPLETO","categoria":"Hogar","precio":10}' "$APP/api/productos"
echo "Últimos errores en laravel.log:"
grep -h "local.ERROR" storage/logs/laravel*.log 2>/dev/null | tail -n 2 | cut -c1-160

echo; echo "== Escenario 4. Operaciones con mayor tiempo de respuesta (desde /metrics)"
curl -s "$APP/metrics" | python3 -c "
import sys, re
cnt, tot = {}, {}
for l in sys.stdin:
    m = re.match(r'laravel_route_(requests_total|duration_seconds_sum)\{route=\"(.+)\"\} ([0-9.]+)', l)
    if m:
        (cnt if m[1]=='requests_total' else tot)[m[2]] = float(m[3])
filas = sorted(((tot[r]/cnt[r]*1000, r, int(cnt[r])) for r in cnt if cnt[r]), reverse=True)
print(f\"{'Ruta':45} {'Peticiones':>10} {'Promedio':>12}\")
for p, r, c in filas[:8]:
    print(f'{r:45} {c:>10} {p:>9.1f} ms')
"
