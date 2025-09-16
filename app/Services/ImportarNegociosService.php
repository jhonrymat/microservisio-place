<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use App\Models\LugarVinculado;
use App\Models\InstantaneaLugar;
use Illuminate\Support\Facades\DB;

class ImportarNegociosService
{
    protected Client $http;
    protected string $apiKey;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 15]);
        $this->apiKey = (string) config('services.google.places.key', '');
        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('Falta GOOGLE_PLACES_API_KEY en config/services.php y .env');
        }
    }

    /**
     * Buscar por nombre + ciudad usando Places v1 (searchText).
     * Puedes pasar lat/lng para sesgar (mejor precisión).
     */
    public function importarPorNombre(
        string $nombre,
        ?string $ciudad = null,
        ?float $lat = null,
        ?float $lng = null,
        ?string $batchToken = null,
        ?string $searchKey = null,
        int $pageSize = 10,            // 👈 NUEVO
        bool $usarRestriccion = false  // 👈 NUEVO: true = locationRestriction en vez de bias
    ): array {
        $textQuery = $ciudad ? "{$nombre}, {$ciudad}" : $nombre;

        $batchToken = $batchToken ?: (string) Str::uuid();
        $searchKey = $searchKey ?: $textQuery;

        $headers = [
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' =>
                'places.id,places.displayName,places.formattedAddress,places.location,' .
                'places.types,places.rating,places.userRatingCount,places.photos',
        ];

        $body = [
            'textQuery' => $textQuery,
            'languageCode' => 'es',
            'regionCode' => 'CO',
            'pageSize' => max(1, min($pageSize, 20)), // 👈 tope razonable
        ];

        // Si nos pasan coords, preferir bias o restriction
        if ($lat !== null && $lng !== null) {
            if ($usarRestriccion) {
                // rectángulo +/- ~4km (ajustable)
                $delta = 0.04; // grados aprox
                $body['locationRestriction'] = [
                    'rectangle' => [
                        'low' => ['latitude' => $lat - $delta, 'longitude' => $lng - $delta],
                        'high' => ['latitude' => $lat + $delta, 'longitude' => $lng + $delta],
                    ]
                ];
            } else {
                $body['locationBias'] = [
                    'circle' => [
                        'center' => ['latitude' => $lat, 'longitude' => $lng],
                        'radius' => 8000, // 8km de radio, ajusta si quieres
                    ],
                ];
            }
        }

        $res = $this->http->post('https://places.googleapis.com/v1/places:searchText', [
            'headers' => $headers,
            'json' => $body,
        ]);

        $json = json_decode($res->getBody()->getContents(), true);
        $candidatos = $json['places'] ?? [];

        foreach ($candidatos as $place) {
            $this->guardarSnapshotMinimo($place, $batchToken, $searchKey);
        }

        \Log::info('Places v1 searchText response', [
            'count' => count($candidatos),
            'query' => $textQuery,
            'batch' => $batchToken,
        ]);

        return $candidatos;
    }


    /**
     * Guardar snapshot mínimo (no vincula a un negocio de plataforma aún).
     * Vincularás cuando el admin confirme cuál place_id corresponde.
     */
    protected function guardarSnapshotMinimo(array $place, string $batchToken, string $searchKey): void
    {
        $placeId = $place['id'] ?? null;
        if (!$placeId)
            return;

        // Persistimos como snapshot; TTL corto para candidatos (p.ej., 2 días)
        InstantaneaLugar::updateOrCreate(
            ['id_lugar' => $placeId],
            [
                'carga' => json_encode($place),
                'fecha_fetched' => now(),
                'fecha_expiracion_ttl' => now()->addDays(2),
                'batch_token' => $batchToken,
                'search_key' => $searchKey,
            ]
        );
    }

    /**
     * Cuando el admin elige un candidato, trae detalles completos.
     */
    public function obtenerDetalles(string $placeId): array
    {
        $headers = [
            'X-Goog-Api-Key' => $this->apiKey,
            // FieldMask para detalle (ajusta a lo que necesites mapear)
            'X-Goog-FieldMask' => 'id,displayName,formattedAddress,location,internationalPhoneNumber,websiteUri,regularOpeningHours,types,photos,rating,userRatingCount'
        ];

        $res = $this->http->get("https://places.googleapis.com/v1/places/{$placeId}", [
            'headers' => $headers,
        ]);

        $json = json_decode($res->getBody()->getContents(), true);

        // Snapshot con TTL de 7 días para detalle
        InstantaneaLugar::updateOrCreate(
            ['id_lugar' => $placeId],
            [
                'carga' => json_encode($json),
                'fecha_fetched' => now(),
                'fecha_expiracion_ttl' => now()->addDays(7),
            ]
        );

        return $json;
    }

    /**
     * Vincular un place_id al ID de la plataforma (cuando el admin confirma).
     */
    public function vincularConPlataforma(int $idPlataforma, string $placeId, float $confianza = 0.9, bool $forzarReasignacion = false): \App\Models\LugarVinculado
    {
        $existentePorPlace = LugarVinculado::where('id_lugar', $placeId)->first();

        if ($existentePorPlace && (int) $existentePorPlace->id_negocio_plataforma !== $idPlataforma) {
            // el place ya está ligado a otro listing
            if (!$forzarReasignacion) {
                // lanza excepción clara (o retorna un estado para que la UI pregunte)
                throw new \RuntimeException(
                    "El place_id ya está vinculado al listing {$existentePorPlace->id_negocio_plataforma}. " .
                    "Usa rebind (forzarReasignacion=true) si quieres moverlo."
                );
            }

            // Rebind: mover la relación al nuevo listing
            $existentePorPlace->id_negocio_plataforma = $idPlataforma;
            $existentePorPlace->confianza = $confianza;
            $existentePorPlace->estrategia_coincidencia = 'rebind';
            $existentePorPlace->estado = 'vinculado';
            $existentePorPlace->ultima_verificacion = now();
            $existentePorPlace->save();

            DB::connection('platform')->table('listing')
                ->where('id', $idPlataforma)
                ->update(['google_place_id' => $placeId, 'date_modified' => time()]);
            return $existentePorPlace;
        }



        // Si no existe por place, crea/actualiza indexando por id_lugar (coherente con la UNIQUE)
        return LugarVinculado::updateOrCreate(
            ['id_lugar' => $placeId],
            [
                'id_negocio_plataforma' => $idPlataforma,
                'confianza' => $confianza,
                'estrategia_coincidencia' => $existentePorPlace ? 'rebind' : 'búsqueda_texto',
                'estado' => 'vinculado',
                'ultima_verificacion' => now(),
            ]
        );
    }

}
