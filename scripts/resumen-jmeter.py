#!/usr/bin/env python3
"""Resume un archivo de resultados de JMeter (.jtl en CSV) por operación.

Uso:
    python3 scripts/resumen-jmeter.py jmeter/resultados/antes.jtl
    python3 scripts/resumen-jmeter.py jmeter/resultados/antes.jtl --csv > antes.csv
"""
import csv
import statistics
import sys
from collections import defaultdict

LIMITE_LENTO_MS = 2000  # parámetro de evaluación: solicitudes que superan 2 segundos


def percentil(valores, p):
    if not valores:
        return 0
    orden = sorted(valores)
    k = max(0, min(len(orden) - 1, round(p / 100 * len(orden) + 0.5) - 1))
    return orden[k]


def resumir(ruta):
    por_operacion = defaultdict(list)
    inicio, fin = None, None
    with open(ruta, newline="", encoding="utf-8") as f:
        for fila in csv.DictReader(f):
            ms = int(fila["elapsed"])
            ok = fila["success"].lower() == "true"
            codigo = fila.get("responseCode", "")
            por_operacion[fila["label"]].append((ms, ok, codigo))
            ts = int(fila["timeStamp"])
            inicio = ts if inicio is None else min(inicio, ts)
            fin = ts + ms if fin is None else max(fin, ts + ms)

    duracion_s = max(1, (fin - inicio) / 1000) if inicio else 1
    filas = []
    todas = []
    for etiqueta in sorted(por_operacion):
        datos = por_operacion[etiqueta]
        tiempos = [d[0] for d in datos]
        todas.extend(datos)
        filas.append(fila_resumen(etiqueta, datos, tiempos, duracion_s))
    filas.append(fila_resumen("TOTAL", todas, [d[0] for d in todas], duracion_s))
    return filas


def fila_resumen(etiqueta, datos, tiempos, duracion_s):
    errores = sum(1 for d in datos if not d[1])
    e5xx = sum(1 for d in datos if d[2].startswith("5"))
    return {
        "operacion": etiqueta,
        "muestras": len(datos),
        "promedio_ms": round(statistics.mean(tiempos)) if tiempos else 0,
        "p90_ms": percentil(tiempos, 90),
        "p95_ms": percentil(tiempos, 95),
        "max_ms": max(tiempos) if tiempos else 0,
        "lentas_2s": sum(1 for t in tiempos if t > LIMITE_LENTO_MS),
        "error_pct": round(100 * errores / len(datos), 2) if datos else 0,
        "errores_5xx": e5xx,
        "rps": round(len(datos) / duracion_s, 1),
    }


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    filas = resumir(sys.argv[1])
    columnas = list(filas[0].keys())
    if "--csv" in sys.argv:
        w = csv.DictWriter(sys.stdout, fieldnames=columnas)
        w.writeheader()
        w.writerows(filas)
        return
    anchos = {c: max(len(c), *(len(str(f[c])) for f in filas)) for c in columnas}
    print("  ".join(c.ljust(anchos[c]) for c in columnas))
    print("  ".join("-" * anchos[c] for c in columnas))
    for f in filas:
        print("  ".join(str(f[c]).ljust(anchos[c]) for c in columnas))


if __name__ == "__main__":
    main()
