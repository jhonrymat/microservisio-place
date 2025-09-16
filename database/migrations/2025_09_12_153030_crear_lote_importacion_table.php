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
        Schema::create('lote_importacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');  // Nombre del archivo importado
            $table->integer('total_filas');  // Total de registros
            $table->integer('filas_procesadas');  // Cuántas filas se procesaron
            $table->enum('estado', ['pendiente', 'procesando', 'completado', 'fallido']);
            $table->timestamps();
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lote_importacion');
    }
};
