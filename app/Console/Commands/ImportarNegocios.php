<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ImportarNegociosService;
use Illuminate\Support\Str;

class ImportarNegocios extends Command
{
    // Ejecuta así:
    // php artisan importar:negocios "Panadería El Trigal" "Acacías, Meta, Colombia" --pageSize=20 --lat=3.986 --lng=-73.765 --strict=0
    protected $signature = 'importar:negocios
        {nombre}
        {ciudad?}
        {--lat=}
        {--lng=}
        {--pageSize=15}    # 👈 nuevo, default 15
        {--strict=0}';     # 👈 nuevo, default 0 (bias). 1 = restriction

    protected $description = 'Busca candidatos en Google Places y guarda snapshots + miniaturas para previsualización.';

    public function handle(ImportarNegociosService $places)
    {
        $nombre = (string) $this->argument('nombre');
        $ciudad = $this->argument('ciudad');
        $lat = $this->option('lat') !== null ? (float) $this->option('lat') : null;
        $lng = $this->option('lng') !== null ? (float) $this->option('lng') : null;

        // Construimos una clave legible para esta búsqueda
        $searchKey = $ciudad ? "{$nombre}, {$ciudad}" : $nombre;

        // Generamos un token de lote (UUID) para agrupar los resultados de ESTA búsqueda
        $batch = (string) Str::uuid();

        $pageSize = (int) $this->option('pageSize');     // default 15 si no pasas nada
        $usarRestriccion = (bool) ((int) $this->option('strict'));

        $this->info("Buscando: {$searchKey}" . ($lat !== null && $lng !== null ? " (bias {$lat},{$lng})" : ''));
        $this->info("Lote: {$batch}");

        // Pasamos batch y searchKey al servicio
        $candidatos = $places->importarPorNombre(
            $nombre,
            $ciudad,
            $lat,
            $lng,
            $batch,
            $searchKey,
            $pageSize,
            $usarRestriccion
        );

        $this->info('Candidatos: ' . count($candidatos));

        // Prefetch de hasta 3 miniaturas por candidato (320px)
        if (!empty($candidatos)) {
            $this->info('Descargando miniaturas para previsualización (máx 3 por lugar) ...');
            app(\App\Services\FotosService::class)->prefetchMiniaturasCandidatos($candidatos, 3, 320);
            $this->info('Miniaturas listas ✅');
        }

        return self::SUCCESS;
    }
}
