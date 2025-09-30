<?php

// app/Console/Commands/LimpiarFotosHuerfanas.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FotosService;

class LimpiarFotosHuerfanas extends Command
{
    protected $signature = 'fotos:limpiar-huerfanas';

    protected $description = 'Elimina fotos que no tienen lugar asociado (mantenimiento)';

    public function handle(FotosService $fotosService)
    {
        $this->info('Iniciando limpieza de fotos huérfanas...');

        $eliminadas = $fotosService->limpiarFotosHuerfanas();

        $this->info("✅ Limpieza completada: {$eliminadas} fotos eliminadas");

        return self::SUCCESS;
    }
}
