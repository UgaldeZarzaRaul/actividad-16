<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Genera datos de prueba para que la diferencia de rendimiento sea medible.
// Uso: php artisan db:seed --class=ProductoSeeder
class ProductoSeeder extends Seeder
{
    private const TOTAL = 200_000;

    private const LOTE = 2_000;

    private const CATEGORIAS = [
        'Electrónica', 'Papelería', 'Hogar', 'Deportes', 'Ropa', 'Juguetes',
        'Herramientas', 'Alimentos', 'Belleza', 'Mascotas', 'Oficina', 'Libros',
    ];

    public function run(): void
    {
        $now = now();

        for ($inicio = 1; $inicio <= self::TOTAL; $inicio += self::LOTE) {
            $filas = [];
            for ($i = $inicio; $i < $inicio + self::LOTE && $i <= self::TOTAL; $i++) {
                $categoria = self::CATEGORIAS[$i % count(self::CATEGORIAS)];
                $filas[] = [
                    'sku' => sprintf('SKU-%07d', $i),
                    'nombre' => "Producto {$i} de {$categoria}",
                    'categoria' => $categoria,
                    'precio' => round(mt_rand(1000, 999999) / 100, 2),
                    'existencia' => mt_rand(0, 500),
                    'descripcion' => str_repeat("Descripción del producto {$i}. ", 4),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('productos')->insert($filas);
        }
    }
}
