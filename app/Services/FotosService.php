<?php

// app/Services/FotosService.php
namespace App\Services;

use App\Models\FotoLocal;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class FotosService
{
    protected Client $http;
    protected string $apiKey;

    // Usaremos SIEMPRE el disco 'public' para imágenes
    private string $disk = 'public';

    public function __construct()
    {
        $this->http = new Client(['timeout' => 30]);
        $this->apiKey = (string) config('services.google.places.key', '');
        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('Falta GOOGLE_PLACES_API_KEY');
        }
    }

    /**
     * Descarga UNA foto desde Places Photos v1 y la guarda en storage local.
     * Devuelve el modelo FotoLocal.
     */
    // app/Services/FotosService.php

    // app/Services/FotosService.php
    public function descargarFoto(string $placeId, string $photoName, int $maxWidth, string $sizeLabel, array $attrib = []): FotoLocal
    {
        $res = $this->http->get("https://places.googleapis.com/v1/{$photoName}/media", [
            'headers' => ['X-Goog-Api-Key' => $this->apiKey],
            'query' => ['maxWidthPx' => $maxWidth],
            'stream' => true,
        ]);

        $mime = $res->getHeaderLine('Content-Type') ?: 'image/jpeg';
        $ext = $this->extensionPorMime($mime);
        $file = 'photos/' . date('Y/m/d') . '/' . Str::random(20) . '.' . $ext;

        $bytes = $res->getBody()->getContents();
        // 👇 Guardar en disco público
        Storage::disk($this->disk)->put($file, $bytes, ['visibility' => 'public']);

        $size = @getimagesizefromstring($bytes);
        $width = $size[0] ?? null;
        $height = $size[1] ?? null;

        $hash = hash('sha256', $photoName);

        return FotoLocal::updateOrCreate(
            ['photo_name_hash' => $hash, 'size_label' => $sizeLabel],
            [
                'place_id' => $placeId,
                'photo_name' => $photoName,
                'path' => $file,     // 👈 relativo dentro del disco 'public'
                'mime' => $mime,
                'width' => $width,
                'height' => $height,
                'author_name' => $attrib['displayName'] ?? null,
                'author_uri' => $attrib['uri'] ?? null,
                'fetched_at' => now(),
            ]
        );
    }



    protected function extensionPorMime(string $mime): string
    {
        return match ($mime) {
            'image/webp' => 'webp',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    /**
     * Previsualización: de una lista de candidatos "places", descarga HASTA 3 miniaturas por lugar.
     * $places: arreglo como viene de searchText (cada item con ['id','photos'[], ...]).
     */
    public function prefetchMiniaturasCandidatos(array $places, int $maxPorLugar = 3, int $ancho = 320): void
    {
        foreach ($places as $p) {
            $placeId = $p['id'] ?? null;
            if (!$placeId)
                continue;

            $photos = $p['photos'] ?? [];
            $count = 0;

            foreach ($photos as $photo) {
                $name = $photo['name'] ?? null;
                if (!$name)
                    continue;

                $attrib = ($photo['authorAttributions'][0] ?? []) ?: [];
                $this->descargarFoto($placeId, $name, $ancho, 'thumb', $attrib);

                if (++$count >= $maxPorLugar)
                    break;
            }
        }
    }

    /**
     * Para el lugar seleccionado: descarga TODAS las fotos disponibles en uno o más tamaños.
     * $detalles: json de places/{placeId} con 'photos' (usa obtenerDetalles() antes).
     */
    public function importarFotosDeLugarSeleccionado(
        array $detalles,
        array $sizes = [
            ['label' => 'thumb', 'w' => 400],
            ['label' => 'cover', 'w' => 1200],
            ['label' => 'full',  'w' => 2048],  
        ]
    ): array {
        $placeId = $detalles['id'] ?? null;
        if (!$placeId)
            return [];

        $out = [];
        foreach (($detalles['photos'] ?? []) as $photo) {
            $name = $photo['name'] ?? null;
            if (!$name)
                continue;

            $attrib = ($photo['authorAttributions'][0] ?? []) ?: [];
            foreach ($sizes as $s) {
                $out[] = $this->descargarFoto($placeId, $name, (int) $s['w'], (string) $s['label'], $attrib);
            }
        }
        return $out;
    }

    /**
     * URLs públicas (estáticas) desde el disco 'public'
     */
    public function construirUrlsFotos(string $placeId, ?string $sizeLabel = 'full', int $max = 20): array
    {
        $q = FotoLocal::where('place_id', $placeId);
        if ($sizeLabel)
            $q->where('size_label', $sizeLabel);

        return $q->orderBy('id')
            ->limit($max)
            ->get()
            ->map(fn($f) => Storage::disk($this->disk)->url($f->path)) // 👈 /storage/...
            ->all();
    }
}

