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
        Schema::create('lugar_vinculado', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_negocio_plataforma')->unsigned();  // Referencia a la plataforma
            $table->string('id_lugar')->unique();  // ID del lugar en Google Places
            $table->decimal('confianza', 5, 2);  // Confianza entre 0 y 1
            $table->string('estrategia_coincidencia');  // 'búsqueda_texto', 'manual', 'rebind'
            $table->enum('estado', ['vinculado', 'necesita_revisión', 'inválido']);
            $table->timestamp('ultima_verificacion')->nullable();
            $table->timestamps();

            // No estamos usando claves foráneas por el diseño del microservicio
        });
    }




    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lugar_vinculado');
    }
};
