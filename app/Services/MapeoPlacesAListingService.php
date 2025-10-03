<?php

namespace App\Services;

class MapeoPlacesAListingService
{
    /**
     * Recibe el JSON de detalles (Places v1) y devuelve un payload
     * listo para aplicar sobre la tabla `listing` de la plataforma.
     *
     * @param array $place Datos del lugar desde Google Places API v1
     * @param array $opciones Opciones adicionales (ej: imágenes seleccionadas)
     * @return array Payload para sincronizar
     */
    public function mapear(array $place, array $opciones = []): array
    {
        $out = [];

        // ========== INFORMACIÓN BÁSICA ==========
        $out['name'] = data_get($place, 'displayName.text');
        $out['address'] = data_get($place, 'formattedAddress');
        $out['phone'] = data_get($place, 'internationalPhoneNumber');
        $out['website'] = data_get($place, 'websiteUri');

        // ========== UBICACIÓN ==========
        $out['latitude'] = (string) data_get($place, 'location.latitude');
        $out['longitude'] = (string) data_get($place, 'location.longitude');

        // ========== DATOS DE GOOGLE ==========
        $out['google_place_id'] = data_get($place, 'id');
        $out['google_rating'] = data_get($place, 'rating');
        $out['google_user_ratings_total'] = data_get($place, 'userRatingCount');
        $out['google_primary_type'] = data_get($place, 'types.0');
        $out['google_maps_uri'] = data_get($place, 'googleMapsUri');
        $out['google_last_sync_at'] = now();

        // ========== DESCRIPCIÓN (Editorial Summary) ==========
        $editorialSummary = data_get($place, 'editorialSummary.text');
        if ($editorialSummary) {
            $out['description'] = $editorialSummary;
        }

        // ========== RANGO DE PRECIOS ==========
        // Google devuelve: PRICE_LEVEL_FREE, PRICE_LEVEL_INEXPENSIVE, PRICE_LEVEL_MODERATE, PRICE_LEVEL_EXPENSIVE, PRICE_LEVEL_VERY_EXPENSIVE
        $priceLevel = data_get($place, 'priceLevel');
        if ($priceLevel) {
            $out['price_range'] = $this->mapearPriceLevel($priceLevel);
        }

        // ========== HORARIOS DE APERTURA ==========
        $openingHours = data_get($place, 'regularOpeningHours');
        if ($openingHours) {
            $out['opened_minutes'] = $this->extraerMinutosApertura($openingHours);
            $out['closed_minutes'] = $this->extraerMinutosCierre($openingHours);
        }

        // ========== ESTADO DEL NEGOCIO ==========
        $businessStatus = data_get($place, 'businessStatus');
        if ($businessStatus) {
            $out['status'] = $this->mapearBusinessStatus($businessStatus);
        }

        // ========== FOTOS ==========
        $placeId = (string) data_get($place, 'id');
        if ($placeId) {
            $this->mapearFotos($placeId, $out, $opciones);
        }

        // ========== TIPOS/CATEGORÍAS ==========
        $types = data_get($place, 'types', []);
        if (!empty($types)) {
            $out['google_types'] = json_encode($types);
            // Aquí podrías mapear a tus categorías internas si tienes MapaCategoria
        }

        return array_filter($out, fn($value) => $value !== null);
    }

    /**
     * Mapea las fotos del lugar
     */
    protected function mapearFotos(string $placeId, array &$out, array $opciones): void
    {
        $fotosSvc = app(\App\Services\FotosService::class);

        // ========== GALERÍA ==========
        $urlsGaleria = $fotosSvc->construirUrlsFotos($placeId, 'full', 30);
        if (empty($urlsGaleria)) {
            $urlsGaleria = $fotosSvc->construirUrlsFotos($placeId, 'cover', 30);
        }
        if (empty($urlsGaleria)) {
            $urlsGaleria = $fotosSvc->construirUrlsFotos($placeId, 'thumb', 30);
        }

        if (!empty($urlsGaleria)) {
            $out['photos'] = json_encode($urlsGaleria, JSON_UNESCAPED_SLASHES);
        }

        // ========== THUMBNAIL Y COVER (Con selección manual) ==========

        // Si el admin seleccionó imágenes específicas, usarlas
        if (!empty($opciones['thumbnail_id'])) {
            $thumb = \App\Models\FotoLocal::find($opciones['thumbnail_id']);
            $out['listing_thumbnail'] = $thumb ? route('media.local', $thumb->id) : null;
        } else {
            // Comportamiento por defecto: primera thumb
            $thumbs = $fotosSvc->construirUrlsFotos($placeId, 'thumb', 1);
            $out['listing_thumbnail'] = $thumbs[0] ?? null;
        }

        if (!empty($opciones['cover_id'])) {
            $cover = \App\Models\FotoLocal::find($opciones['cover_id']);
            $out['listing_cover'] = $cover ? route('media.local', $cover->id) : null;
        } else {
            // Comportamiento por defecto: primera cover
            $covers = $fotosSvc->construirUrlsFotos($placeId, 'cover', 1);
            $out['listing_cover'] = $covers[0] ?? null;
        }
    }

    /**
     * Mapea el price level de Google a un formato más legible
     */
    protected function mapearPriceLevel(string $priceLevel): string
    {
        return match($priceLevel) {
            'PRICE_LEVEL_FREE' => 'free',
            'PRICE_LEVEL_INEXPENSIVE' => 'inexpensive',
            'PRICE_LEVEL_MODERATE' => 'moderate',
            'PRICE_LEVEL_EXPENSIVE' => 'expensive',
            'PRICE_LEVEL_VERY_EXPENSIVE' => 'very_expensive',
            default => 'moderate',
        };
    }

    /**
     * Mapea el business status de Google al status de la plataforma
     */
    protected function mapearBusinessStatus(string $businessStatus): string
    {
        return match($businessStatus) {
            'OPERATIONAL' => 'active',
            'CLOSED_TEMPORARILY' => 'inactive',
            'CLOSED_PERMANENTLY' => 'deleted',
            default => 'active',
        };
    }

    /**
     * Extrae los minutos de apertura desde regularOpeningHours
     * Retorna el horario de apertura más común (lunes)
     */
    protected function extraerMinutosApertura(array $openingHours): ?int
    {
        $periods = data_get($openingHours, 'periods', []);

        if (empty($periods)) {
            return null;
        }

        // Buscar el primer período de lunes (día 0 en Google)
        foreach ($periods as $period) {
            $open = data_get($period, 'open');
            if ($open && data_get($open, 'day') === 0) { // Lunes
                $hour = data_get($open, 'hour', 0);
                $minute = data_get($open, 'minute', 0);
                return ($hour * 60) + $minute;
            }
        }

        // Si no hay lunes, usar el primer período disponible
        $firstOpen = data_get($periods, '0.open');
        if ($firstOpen) {
            $hour = data_get($firstOpen, 'hour', 0);
            $minute = data_get($firstOpen, 'minute', 0);
            return ($hour * 60) + $minute;
        }

        return null;
    }

    /**
     * Extrae los minutos de cierre desde regularOpeningHours
     */
    protected function extraerMinutosCierre(array $openingHours): ?int
    {
        $periods = data_get($openingHours, 'periods', []);

        if (empty($periods)) {
            return null;
        }

        // Buscar el primer período de lunes
        foreach ($periods as $period) {
            $close = data_get($period, 'close');
            if ($close && data_get($period, 'open.day') === 0) {
                $hour = data_get($close, 'hour', 0);
                $minute = data_get($close, 'minute', 0);
                return ($hour * 60) + $minute;
            }
        }

        // Si no hay lunes, usar el primer período disponible
        $firstClose = data_get($periods, '0.close');
        if ($firstClose) {
            $hour = data_get($firstClose, 'hour', 0);
            $minute = data_get($firstClose, 'minute', 0);
            return ($hour * 60) + $minute;
        }

        return null;
    }

    /**
     * Obtiene todas las fotos disponibles para selección
     * Útil para el selector de imágenes en la UI
     */
    public function obtenerFotosParaSeleccion(string $placeId): array
    {
        $fotos = \App\Models\FotoLocal::where('place_id', $placeId)
            ->orderBy('id')
            ->get()
            ->groupBy('size_label');

        return [
            'thumbs' => $fotos->get('thumb', collect())->map(function($foto) {
                return [
                    'id' => $foto->id,
                    'url' => route('media.local', $foto->id),
                    'width' => $foto->width,
                    'height' => $foto->height,
                    'author' => $foto->author_name,
                ];
            })->toArray(),
            'covers' => $fotos->get('cover', collect())->map(function($foto) {
                return [
                    'id' => $foto->id,
                    'url' => route('media.local', $foto->id),
                    'width' => $foto->width,
                    'height' => $foto->height,
                    'author' => $foto->author_name,
                ];
            })->toArray(),
            'fulls' => $fotos->get('full', collect())->map(function($foto) {
                return [
                    'id' => $foto->id,
                    'url' => route('media.local', $foto->id),
                    'width' => $foto->width,
                    'height' => $foto->height,
                    'author' => $foto->author_name,
                ];
            })->toArray(),
        ];
    }
}
