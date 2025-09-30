<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FotosService;

class EliminarFotosLugar extends Command
{
    protected $signature = 'fotos:eliminar {placeId} {--size=}';

    protected $description = 'Elimina fotos de un lugar específico';

    public function handle(FotosService $fotosService)
    {
        $placeId = $this->argument('placeId');
        $size = $this->option('size');

        if ($size) {
            $eliminadas = $fotosService->eliminarFotosPorTamano($placeId, $size);
            $this->info("✅ {$eliminadas} fotos '{$size}' eliminadas de {$placeId}");
        } else {
            $eliminadas = $fotosService->eliminarFotosDeUnLugar($placeId);
            $this->info("✅ {$eliminadas} fotos eliminadas de {$placeId}");
        }

        return self::SUCCESS;
    }
}
