<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fotos_locales', function (Blueprint $table) {
            $table->id();

            $table->string('place_id', 255);           // v1 places.id
            $table->text('photo_name');                 // "places/.../photos/..."
            $table->char('photo_name_hash', 64);        // sha256 de photo_name (para indexar/unique)

            $table->string('path', 255);                // ruta local o URL servida por el micro
            $table->string('size_label', 16);           // 'thumb' | 'cover' | 'full'
            $table->string('mime', 64)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();

            $table->string('author_name', 255)->nullable();
            $table->string('author_uri', 512)->nullable();  // ← string, no char

            $table->timestamp('fetched_at')->nullable();

            // Índices
            $table->index('place_id');
            $table->index('size_label');
            $table->index('photo_name_hash');
            $table->index(['place_id', 'size_label']); // útil para listados por place

            // Único por foto (hash) y tamaño
            $table->unique(['photo_name_hash', 'size_label'], 'fotos_namehash_sizelabel_unique');

            // Si algún día quieres timestamps:
            // $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_locales');
    }
};
