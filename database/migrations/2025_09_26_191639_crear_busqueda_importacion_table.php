<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('busqueda_importacion', function (Blueprint $table) {
            $table->id();

            // Parámetros de búsqueda
            $table->string('nombre', 255);
            $table->string('ciudad', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->integer('page_size')->default(15);
            $table->boolean('usar_restriccion')->default(false); // strict mode

            // Datos generados
            $table->string('batch_token', 36)->nullable(); // UUID del lote
            $table->string('search_key', 500)->nullable(); // clave legible

            // Estado y resultados
            $table->enum('estado', ['pendiente', 'ejecutando', 'completado', 'fallido'])->default('pendiente');
            $table->integer('candidatos_encontrados')->nullable();
            $table->text('mensaje_error')->nullable();

            // Fechas
            $table->timestamp('fecha_ejecutada')->nullable();
            $table->timestamps();

            // Índices
            $table->index('estado');
            $table->index('batch_token');
            $table->index('search_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('busqueda_importacion');
    }
};
