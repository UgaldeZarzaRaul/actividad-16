#!/usr/bin/env python3
"""Compara dos ejecuciones de JMeter (antes y después de aplicar una mejora).

Uso:
    python3 scripts/comparar.py jmeter/resultados/antes.jtl jmeter/resultados/despues.jtl
"""
import importlib.util
import pathlib
import sys

spec = importlib.util.spec_from_file_location(
    "resumen", pathlib.Path(__file__).with_name("resumen-jmeter.py"))
resumen = importlib.util.module_from_spec(spec)
spec.loader.exec_module(resumen)


def cambio(a, d, menor_es_mejor=True):
    if a == 0:
        return "—"
    pct = (d - a) / a * 100
    signo = "+" if pct > 0 else ""
    mejora = (pct < 0) == menor_es_mejor
    return f"{signo}{pct:.1f} % {'(mejora)' if mejora and pct != 0 else ''}".strip()


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    antes = {f["operacion"]: f for f in resumen.resumir(sys.argv[1])}
    despues = {f["operacion"]: f for f in resumen.resumir(sys.argv[2])}
    metricas = [("promedio_ms", True), ("p95_ms", True), ("lentas_2s", True),
                ("error_pct", True), ("errores_5xx", True), ("rps", False)]
    print(f"{'Operación':34} {'Métrica':12} {'Antes':>9} {'Después':>9}  Cambio")
    print("-" * 82)
    for op in antes:
        if op not in despues:
            continue
        for m, menor in metricas:
            a, d = antes[op][m], despues[op][m]
            print(f"{op[:34]:34} {m:12} {a:>9} {d:>9}  {cambio(a, d, menor)}")
        print()


if __name__ == "__main__":
    main()
