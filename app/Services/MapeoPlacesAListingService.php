<?php

namespace App\Services;

class MapeoPlacesAListingService
{
    /**
     * Recibe el JSON de detalles (Places v1) y devuelve un payload
     * listo para aplicar sobre la tabla `listing` de la plataforma.
     * Solo incluimos campos que típicamente vienen de Google.
     */
    public function mapear(array $place): array
    {
        $out = [];

        // Campos que ya tenías:
        $out['name'] = data_get($place, 'displayName.text');
        $out['address'] = data_get($place, 'formattedAddress');
        $out['phone'] = data_get($place, 'internationalPhoneNumber');
        $out['website'] = data_get($place, 'websiteUri');
        $out['latitude'] = (string) data_get($place, 'location.latitude');
        $out['longitude'] = (string) data_get($place, 'location.longitude');
        $out['google_place_id'] = data_get($place, 'id');
        $out['google_rating'] = data_get($place, 'rating');
        $out['google_user_ratings_total'] = data_get($place, 'userRatingCount');
        $out['google_primary_type'] = data_get($place, 'types.0');
        $out['google_maps_uri'] = data_get($place, 'googleMapsUri');
        $out['google_last_sync_at'] = now();

        // Fotos
        $placeId = (string) data_get($place, 'id');
        if ($placeId) {
            $fotosSvc = app(\App\Services\FotosService::class);

            // Galería (full si existe; si no, thumb como fallback)
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

            // Thumbnail y Cover
            $thumbs = $fotosSvc->construirUrlsFotos($placeId, 'thumb', 1);
            $covers = $fotosSvc->construirUrlsFotos($placeId, 'cover', 1);

            // Guarda URL absoluta (tu front ya las acepta)
            $out['listing_thumbnail'] = $thumbs[0] ?? null;
            $out['listing_cover'] = $covers[0] ?? null;
        }

        return $out;
    }
}
