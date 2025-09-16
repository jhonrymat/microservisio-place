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
        Schema::create('historial_cambio', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_negocio_plataforma')->unsigned();  // Referencia a la plataforma
            $table->string('campo');  // El campo que fue cambiado
            $table->text('valor_anterior')->nullable();  // Valor anterior
            $table->text('valor_nuevo')->nullable();  // Valor nuevo
            $table->string('fuente');  // 'places_sync', 'manual'
            $table->timestamp('fecha_aplicacion')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamps();

            // Sin claves foráneas por el diseño del microservicio
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_cambio');
    }
};
