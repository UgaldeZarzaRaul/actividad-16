<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MEJORA 1 (estado DESPUÉS): índices para las consultas más frecuentes
// y restricción única en el SKU.
// Ejecutar con: php artisan migrate --path=database/migrations/mejora
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['categoria', 'precio'], 'productos_categoria_precio_index');
            $table->unique('sku', 'productos_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_categoria_precio_index');
            $table->dropUnique('productos_sku_unique');
        });
    }
};
