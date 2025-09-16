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
        Schema::create('bloqueo_campo', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_negocio_plataforma')->unsigned();  // Referencia a la plataforma
            $table->string('campo');  // 'telefono', 'sitio_web', etc.
            $table->boolean('bloqueado')->default(true);  // Indica si está bloqueado
            $table->timestamps();

            // Sin claves foráneas debido a la separación de bases de datos
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bloqueo_campo');
    }
};
