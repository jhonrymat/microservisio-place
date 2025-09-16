<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('mapa_categoria', function (Blueprint $table) {
            $table->string('tipo_google');  // 'restaurante', 'tienda', etc.
            $table->string('categoria_plataforma');  // Categoría de la plataforma
            $table->timestamps();

            $table->primary(['tipo_google']);  // Asegurar que no se dupliquen tipos
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mapa_categoria');
    }
};
