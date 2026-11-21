#!/bin/zsh
# Escenario 5. Solicitudes periódicas para calcular la disponibilidad.
# Uso: ./scripts/disponibilidad.sh [minutos] [intervalo_segundos]
#      APP_URL=http://localhost:8000 ./scripts/disponibilidad.sh 5 5
APP=${APP_URL:-http://localhost:8000}
MINUTOS=${1:-5}
INTERVALO=${2:-5}
TIMEOUT=${TIMEOUT:-5}
SALIDA=${SALIDA:-disponibilidad-$(date +%Y%m%d-%H%M%S).csv}

total=$(( MINUTOS * 60 / INTERVALO ))
ok=0; lentas=0
echo "fecha,codigo,tiempo_s" > "$SALIDA"
echo "Revisando $APP/api/health cada ${INTERVALO}s durante ${MINUTOS} min (timeout ${TIMEOUT}s)..."
for i in $(seq 1 $total); do
  r=$(curl -s -o /dev/null -m "$TIMEOUT" -w "%{http_code},%{time_total}" "$APP/api/health")
  codigo=${r%%,*}; tiempo=${r##*,}
  echo "$(date +%H:%M:%S),$codigo,$tiempo" >> "$SALIDA"
  [[ "$codigo" == "200" ]] && ok=$((ok + 1))
  (( $(echo "$tiempo > 2" | bc -l) )) && lentas=$((lentas + 1))
  printf "\r[%3d/%3d] último: HTTP %s en %ss" $i $total "$codigo" "$tiempo"
  sleep "$INTERVALO"
done
echo
disp=$(echo "scale=2; 100 * $ok / $total" | bc)
echo "Solicitudes: $total  Exitosas: $ok  Lentas (>2s): $lentas"
echo "Disponibilidad: ${disp} %  (objetivo: 95 % o superior)"
echo "Detalle guardado en $SALIDA"
