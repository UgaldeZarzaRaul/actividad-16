#!/usr/bin/env bash
# Verificación de disponibilidad posterior al despliegue (se usa en GitHub Actions).
# Falla (exit 1) si la aplicación no responde correctamente.
# Uso: APP_URL=https://mi-app.ejemplo.com ./scripts/verificar-despliegue.sh
set -u
APP=${APP_URL:-http://localhost:8000}
INTENTOS=${INTENTOS:-10}
ESPERA=${ESPERA:-6}
TIMEOUT=${TIMEOUT:-5}
LIMITE_S=${LIMITE_S:-2}

echo "Verificando despliegue en $APP"
for i in $(seq 1 "$INTENTOS"); do
  cuerpo=$(curl -s -m "$TIMEOUT" -w $'\n%{http_code} %{time_total}' "$APP/api/health" || true)
  meta=$(echo "$cuerpo" | tail -n 1)
  codigo=${meta%% *}; tiempo=${meta##* }
  if [[ "$codigo" == "200" ]] && echo "$cuerpo" | grep -q '"status":"up"'; then
    echo "OK intento $i: /api/health HTTP 200 en ${tiempo}s"
    fallos=0
    for ruta in "/" "/api/productos?categoria=Hogar&min=100&max=5000" "/metrics"; do
      r=$(curl -s -o /dev/null -m "$TIMEOUT" -H "Accept: application/json" -w "%{http_code} %{time_total}" "$APP$ruta")
      c=${r%% *}; t=${r##* }
      estado="OK"
      [[ "$c" != "200" ]] && estado="FALLA" && fallos=$((fallos + 1))
      awk -v t="$t" -v l="$LIMITE_S" 'BEGIN{exit !(t>l)}' && estado="$estado (LENTA > ${LIMITE_S}s)"
      printf "  %-55s HTTP %s  %ss  %s\n" "$ruta" "$c" "$t" "$estado"
    done
    if [[ $fallos -gt 0 ]]; then
      echo "La aplicación responde, pero $fallos endpoint(s) fallaron."
      exit 1
    fi
    echo "Despliegue verificado correctamente."
    exit 0
  fi
  echo "Intento $i/$INTENTOS: HTTP ${codigo:-sin respuesta}. Reintentando en ${ESPERA}s..."
  sleep "$ESPERA"
done
echo "La aplicación no estuvo disponible después de $INTENTOS intentos."
exit 1
