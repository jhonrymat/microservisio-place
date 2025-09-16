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
        Schema::create('instantanea_lugar', function (Blueprint $table) {
            $table->id();
            $table->string('id_lugar')->unique();  // ID del lugar en Google
            $table->json('carga');  // Detalles del lugar como JSON
            $table->timestamp('fecha_fetched')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('fecha_expiracion_ttl')->nullable();
            $table->timestamps();
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instantanea_lugar');
    }
};
