<?php
// app/Services/SincronizarListingService.php

namespace App\Services;

use App\Models\BloqueoCampo;
use App\Models\HistorialCambio;
use Illuminate\Support\Facades\DB;

class SincronizarListingService
{
    /**
     * Aplica un payload de campos a `platform.listing` respetando locks,
     * con opción de forzar algunos campos, y registrando diffs.
     *
     * @param int    $idListing
     * @param array  $payload              Campos => valores a aplicar
     * @param string $fuente               Etiqueta de auditoría
     * @param array  $forzarCampos         Campos en los que se ignorará el lock (opcional)
     * @param bool   $simular              No escribe en DB si true (opcional)
     * @return array                       Diffs aplicados (antes/después)
     */
    // app/Services/SincronizarListingService.php

    public function aplicar(int $idListing, array $payload, string $fuente = 'places_sync', array $forzarCampos = [], bool $simular = false): array
    {
        $conn = DB::connection('platform');
        $tabla = $conn->table('listing');

        // 1) Lista blanca de campos que sí se pueden actualizar
        $permitidos = [
            'name',
            'address',
            'phone',
            'website',
            'latitude',
            'longitude',
            'google_place_id',
            'google_rating',
            'google_user_ratings_total',
            'google_primary_type',
            'google_maps_uri',
            'google_last_sync_at',
            // 👇 agrega estos:
            'photos',             // LONGTEXT OK
            'listing_thumbnail',
            'listing_cover',
        ];

        // 2) Cargar bloqueos (si usas BloqueoCampo, ya lo tienes en falso)
        $bloqueos = BloqueoCampo::where('id_negocio_plataforma', $idListing)
            ->pluck('bloqueado', 'campo');

        // 3) Leer el registro actual
        $actual = (array) $tabla->where('id', $idListing)->first();

        $updates = [];
        $diff = [];

        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $payload))
                continue;

            // respetar locks (si existieran)
            if (!empty($bloqueos[$campo]))
                continue;

            $estaForzado = in_array($campo, $forzarCampos, true);
            $estaBloqueado = ($bloqueos[$campo] ?? false) === true;

            if (!$estaForzado && $estaBloqueado)
                continue;

            $nuevo = $payload[$campo];
            if (is_array($nuevo)) {
                $nuevo = json_encode($nuevo, JSON_UNESCAPED_SLASHES);
            } elseif ($nuevo instanceof \DateTimeInterface) {
                $nuevo = $nuevo->format('Y-m-d H:i:s');
            }

            $viejo = $actual[$campo] ?? null;
            if ($nuevo !== $viejo) {
                $updates[$campo] = $nuevo;
                $diff[$campo] = ['antes' => $viejo, 'despues' => $nuevo];
            }
        }


        if (!empty($updates)) {
            // siempre refrescamos date_modified
            $updates['date_modified'] = time();
            if (!$simular) {
                $tabla->where('id', $idListing)->update($updates);
            }
        }

        return $diff;
    }

}
