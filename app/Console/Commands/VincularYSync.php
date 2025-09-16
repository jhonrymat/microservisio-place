<?php

// app/Console/Commands/VincularYSync.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ImportarNegociosService;
use App\Services\MapeoPlacesAListingService;
use App\Services\SincronizarListingService;
use App\Services\FotosService;
use Illuminate\Support\Facades\DB;

class VincularYSync extends Command
{
    // 🔹 NUEVAS OPCIONES: --fotos=true/false, --max=0 (0 = todas)
    protected $signature = 'ms:vincular-sync {idListing} {placeId} {--fotos=1} {--max=0}';
    protected $description = 'Vincula un placeId a un listing, sincroniza campos y gestiona fotos';

    public function handle(
        ImportarNegociosService $places,
        MapeoPlacesAListingService $mapeo,
        SincronizarListingService $sync,
        FotosService $fotos
    ) {
        $idListing = (int) $this->argument('idListing');
        $placeId = $this->argument('placeId');
        $conFotos = (bool) $this->option('fotos');
        $maxFotos = (int) $this->option('max');

        // 1) Vincular (si ya existe, lo actualiza; también escribe google_place_id en listing)
        $places->vincularConPlataforma($idListing, $placeId, 0.95, true);

        // 2) Traer detalles
        $detalles = $places->obtenerDetalles($placeId);

        // 3) Mapear a payload de listing (incluye google_* y last_sync)
        $payload = $mapeo->mapear($detalles);

        // 4) Aplicar datos (respeta locks + historial)
        $sync->aplicar($idListing, $payload, 'places_sync');

        // 5) (Opcional) Fotos completas del lugar seleccionado
        if ($conFotos) {
            // Si quieres limitar cuántas fotos importar: corta el arreglo
            if ($maxFotos > 0 && !empty($detalles['photos'])) {
                $detalles['photos'] = array_slice($detalles['photos'], 0, $maxFotos);
            }

            $this->info('Descargando fotos del lugar seleccionado...');
            $lista = $fotos->importarFotosDeLugarSeleccionado(
                $detalles,
                [['label' => 'thumb', 'w' => 400], ['label' => 'cover', 'w' => 1200]]
            );

            // Elegir una thumb y una cover (las primeras que existan)
            $thumb = collect($lista)->firstWhere('size_label', 'thumb');
            $cover = collect($lista)->firstWhere('size_label', 'cover');

            // URLs públicas desde nuestro micro (via controller)
            $thumbUrl = $thumb ? route('media.local', $thumb->id) : null;
            $coverUrl = $cover ? route('media.local', $cover->id) : null;

            // Respetar locks: sólo escribir si no están bloqueados
            $locks = \App\Models\BloqueoCampo::where('id_negocio_plataforma', $idListing)
                ->pluck('bloqueado', 'campo');

            $updates = ['date_modified' => time()];

            if ($thumbUrl && !($locks['listing_thumbnail'] ?? false)) {
                $updates['listing_thumbnail'] = $thumbUrl;
            }
            if ($coverUrl && !($locks['listing_cover'] ?? false)) {
                $updates['listing_cover'] = $coverUrl;
            }

            if (count($updates) > 1) {
                DB::connection('platform')->table('listing')->where('id', $idListing)->update($updates);
                $this->info('Thumbnail/Cover actualizados.');
            } else {
                $this->info('Thumbnail/Cover bloqueados por locks o sin fotos disponibles.');
            }
        }

        $this->info("OK → listing {$idListing} sincronizado desde {$placeId}");
    }
}
