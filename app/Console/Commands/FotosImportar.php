<?php
// app/Console/Commands/FotosImportar.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ImportarNegociosService;
use App\Services\FotosService;

class FotosImportar extends Command
{
    protected $signature = 'ms:fotos-importar {placeId} {--max=0}';
    protected $description = 'Descarga fotos de un placeId (todas o limitadas)';

    public function handle(ImportarNegociosService $places, FotosService $fotos)
    {
        $placeId = $this->argument('placeId');
        $max = (int) $this->option('max');

        $det = $places->obtenerDetalles($placeId);
        if ($max > 0 && !empty($det['photos'])) {
            $det['photos'] = array_slice($det['photos'], 0, $max);
        }

        $lista = $fotos->importarFotosDeLugarSeleccionado(
            $det,
            [['label' => 'thumb', 'w' => 400], ['label' => 'cover', 'w' => 1200]]
        );

        $this->info('Fotos importadas: ' . count($lista));
    }
}
