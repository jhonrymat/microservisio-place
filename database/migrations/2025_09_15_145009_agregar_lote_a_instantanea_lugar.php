<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('instantanea_lugar', function (Blueprint $table) {
            // token de lote (UUID/ulid) para agrupar una búsqueda
            $table->string('batch_token', 36)->nullable()->after('id_lugar');
            // clave legible de búsqueda (ej. "Panadería El Trigal | Acacías, Meta, Colombia")
            $table->string('search_key', 255)->nullable()->after('batch_token');

            $table->index('batch_token');
            $table->index('search_key');
        });
    }

    public function down(): void
    {
        Schema::table('instantanea_lugar', function (Blueprint $table) {
            $table->dropIndex(['batch_token']);
            $table->dropIndex(['search_key']);
            $table->dropColumn(['batch_token', 'search_key']);
        });
    }
};
