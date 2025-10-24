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
        $priceLevel = data_get($place, 'priceLevel');
        if ($priceLevel) {
            $out['price_range'] = $this->mapearPriceLevel($priceLevel);
        }

        // ========== HORARIOS DE APERTURA (SIMPLIFICADOS) ==========
        // Solo guarda opened_minutes y closed_minutes para compatibilidad
        // Los horarios completos se guardan en time_configuration por separado
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

        // ========== EMAIL (No viene en la API, pero dejamos el campo) ==========
        // Google Places API no provee email directamente
        // Se podría extraer del website o dejarlo null

        // ========== REDES SOCIALES ==========
        // Google Places no provee redes sociales directamente
        // Podrías intentar extraerlas del website o dejarlas null
        // El campo 'social' espera JSON: {"facebook": "url", "instagram": "url", ...}

        // ========== FOTOS ==========
        $placeId = (string) data_get($place, 'id');
        if ($placeId) {
            $this->mapearFotos($placeId, $out, $opciones);
        }

        // ========== TIPOS/CATEGORÍAS ==========
        $types = data_get($place, 'types', []);
        if (!empty($types)) {
            $out['google_types'] = json_encode($types);
            // Aquí podrías mapear a tus categorías internas
        }

        // ========== CAMPOS ADICIONALES ==========
        // Google Analytics ID - no viene de Google Places
        // Package Expiry Date - gestión interna
        // Certifications - gestión interna
        // Featured - gestión interna

        return array_filter($out, fn($value) => $value !== null);
    }

    /**
     * Extrae y formatea los horarios para la tabla time_configuration
     * Convierte de formato Google ("8:00 AM – 6:00 PM") a formato plataforma ("8:00-18:00")
     *
     * @param array $place Datos del lugar
     * @return array Horarios formateados para time_configuration
     */
    public function extraerHorariosParaConfiguracion(array $place): array
    {
        $openingHours = data_get($place, 'regularOpeningHours');

        if (empty($openingHours)) {
            return [];
        }

        $weekdayDescriptions = data_get($openingHours, 'weekdayDescriptions', []);

        // Inicializar todos los días como NULL
        $horarios = [
            'monday' => null,
            'tuesday' => null,
            'wednesday' => null,
            'thursday' => null,
            'friday' => null,
            'saturday' => null,
            'sunday' => null,
        ];

        // Mapear los días de Google al formato de la tabla
        $diasMap = [
            'Monday' => 'monday',
            'Tuesday' => 'tuesday',
            'Wednesday' => 'wednesday',
            'Thursday' => 'thursday',
            'Friday' => 'friday',
            'Saturday' => 'saturday',
            'Sunday' => 'sunday',
        ];

        foreach ($weekdayDescriptions as $descripcion) {
            // Formato de Google: "Monday: 8:00 AM – 6:00 PM" o "Sunday: Closed"
            foreach ($diasMap as $diaIngles => $diaCampo) {
                if (stripos($descripcion, $diaIngles) === 0) {
                    // Extraer la parte después del ":"
                    $descripcion = preg_replace('/[[:^print:]]/', '', $descripcion);

                    $partes = explode(':', $descripcion, 2);
                    if (count($partes) === 2) {
                        $horario = trim($partes[1]);

                        // Si dice "Closed", dejar como NULL
                        if (stripos($horario, 'Closed') !== false) {
                            $horarios[$diaCampo] = null;
                        } else {
                            // Convertir de "8:00 AM – 6:00 PM" a "8:00-18:00"
                            $horarioConvertido = $this->convertirHorarioAFormato24h($horario);
                            $horarios[$diaCampo] = $horarioConvertido;
                        }
                    }
                    break;
                }
            }
        }

        return $horarios;
    }

    /**
     * Convierte horario de formato 12h a 24h
     * Entrada: "8:00 AM – 6:00 PM" o "8:00 AM - 6:00 PM"
     * Salida: "8:00-18:00"
     *
     * @param string $horario
     * @return string|null
     */
    protected function convertirHorarioAFormato24h(string $horario): ?string
    {
        try {
            // Normalizar caracteres invisibles y espacios especiales
            $horario = str_replace(
                ["\u{202F}", "\u{2009}", "\u{00A0}", "\u{200A}", "\u{200B}", "\u{FEFF}"],
                ' ',
                $horario
            );

            // Reemplazar diferentes tipos de guiones por uno estándar
            $horario = str_replace(['–', '—', ' - ', '–', '-'], '-', $horario);

            // Quitar dobles espacios
            $horario = preg_replace('/\s+/', ' ', $horario);

            // Dividir por el guion
            $partes = explode('-', $horario);

            if (count($partes) !== 2) {
                \Log::warning('Formato de horario no esperado', ['horario' => $horario]);
                return null;
            }

            $apertura = trim($partes[0]);
            $cierre = trim($partes[1]);

            // Convertir ambas partes a formato 24h
            $aperturaConvertida = $this->convertirHora12a24($apertura);
            $cierreConvertida = $this->convertirHora12a24($cierre);

            if ($aperturaConvertida === null || $cierreConvertida === null) {
                return null;
            }

            return $aperturaConvertida . '-' . $cierreConvertida;
        } catch (\Exception $e) {
            \Log::error('Error al convertir horario', [
                'horario' => $horario,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    /**
     * Convierte una hora individual de 12h a 24h
     * Entrada: "8:00 AM" o "6:00 PM"
     * Salida: "8:00" o "18:00"
     *
     * @param string $hora
     * @return string|null
     */
    protected function convertirHora12a24(string $hora): ?string
    {
        try {
            $hora = trim($hora);

            // Determinar si es AM o PM
            $esAM = stripos($hora, 'AM') !== false;
            $esPM = stripos($hora, 'PM') !== false;

            if (!$esAM && !$esPM) {
                \Log::warning('Formato de hora sin AM/PM', ['hora' => $hora]);
                return null;
            }

            // Remover AM/PM y espacios
            $horaSinPeriodo = trim(str_ireplace(['AM', 'PM'], '', $hora));

            // Separar hora y minutos
            $partes = explode(':', $horaSinPeriodo);

            if (count($partes) !== 2) {
                \Log::warning('Formato de hora inválido', ['hora' => $hora]);
                return null;
            }

            $horas = (int) $partes[0];
            $minutos = (int) $partes[1];

            // Convertir a formato 24h
            if ($esPM && $horas !== 12) {
                $horas += 12;
            } elseif ($esAM && $horas === 12) {
                // 12:00 AM es medianoche (00:00)
                $horas = 0;
            }

            // Formatear con ceros a la izquierda si es necesario
            return sprintf('%d:%02d', $horas, $minutos);

        } catch (\Exception $e) {
            \Log::error('Error al convertir hora individual', [
                'hora' => $hora,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Guarda los horarios en la tabla time_configuration
     *
     * @param int $listingId ID del listing
     * @param array $place Datos del lugar de Google
     * @return void
     */
    public function guardarHorarios(int $listingId, array $place): void
    {
        $horarios = $this->extraerHorariosParaConfiguracion($place);

        if (empty($horarios)) {
            return;
        }

        // Agregar el listing_id
        $horarios['listing_id'] = $listingId;

        // Usar la conexión de platform
        $conn = \DB::connection('platform');

        // Verificar si ya existe un registro
        $existe = $conn->table('time_configuration')
            ->where('listing_id', $listingId)
            ->exists();

        if ($existe) {
            // Actualizar
            $conn->table('time_configuration')
                ->where('listing_id', $listingId)
                ->update($horarios);

            \Log::info('Horarios actualizados en time_configuration', [
                'listing_id' => $listingId
            ]);
        } else {
            // Insertar
            $conn->table('time_configuration')->insert($horarios);

            \Log::info('Horarios insertados en time_configuration', [
                'listing_id' => $listingId
            ]);
        }
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
        if (!empty($opciones['thumbnail_id'])) {
            $thumb = \App\Models\FotoLocal::find($opciones['thumbnail_id']);
            $out['listing_thumbnail'] = $thumb ? route('media.local', $thumb->id) : null;
        } else {
            $thumbs = $fotosSvc->construirUrlsFotos($placeId, 'thumb', 1);
            $out['listing_thumbnail'] = $thumbs[0] ?? null;
        }

        if (!empty($opciones['cover_id'])) {
            $cover = \App\Models\FotoLocal::find($opciones['cover_id']);
            $out['listing_cover'] = $cover ? route('media.local', $cover->id) : null;
        } else {
            $covers = $fotosSvc->construirUrlsFotos($placeId, 'cover', 1);
            $out['listing_cover'] = $covers[0] ?? null;
        }
    }

    /**
     * Mapea el price level de Google a un formato más legible
     */
    protected function mapearPriceLevel(string $priceLevel): string
    {
        return match ($priceLevel) {
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
        return match ($businessStatus) {
            'OPERATIONAL' => 'active',
            'CLOSED_TEMPORARILY' => 'inactive',
            'CLOSED_PERMANENTLY' => 'deleted',
            default => 'active',
        };
    }

    /**
     * Extrae los minutos de apertura desde regularOpeningHours
     */
    protected function extraerMinutosApertura(array $openingHours): ?int
    {
        $periods = data_get($openingHours, 'periods', []);

        if (empty($periods)) {
            return null;
        }

        // Buscar el primer período de lunes (día 1 en Google Places API v1)
        foreach ($periods as $period) {
            $open = data_get($period, 'open');
            if ($open && data_get($open, 'day') === 1) { // Lunes
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
            if ($close && data_get($period, 'open.day') === 1) {
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
}
