<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla del caso de estudio. Se crea intencionalmente SIN índices en las columnas
// de búsqueda para reproducir el problema de "consultas lentas" (estado ANTES).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30);
            $table->string('nombre', 120);
            $table->string('categoria', 60);
            $table->decimal('precio', 10, 2);
            $table->unsignedInteger('existencia')->default(0);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
