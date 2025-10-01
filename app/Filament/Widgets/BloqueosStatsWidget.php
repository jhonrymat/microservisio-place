<?php

// app/Filament/Widgets/BloqueosStatsWidget.php
namespace App\Filament\Widgets;

use App\Models\BloqueoCampo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BloqueosStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Total de listings con bloqueos
        $listingsConBloqueos = BloqueoCampo::where('bloqueado', true)
            ->distinct('id_negocio_plataforma')
            ->count('id_negocio_plataforma');

        // Total de campos bloqueados
        $totalCamposBloqueados = BloqueoCampo::where('bloqueado', true)->count();

        // Campos más bloqueados
        $camposMasBloqueados = BloqueoCampo::where('bloqueado', true)
            ->select('campo')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('campo')
            ->orderByDesc('total')
            ->limit(3)
            ->get();

        $topCampos = $camposMasBloqueados->map(function ($item) {
            return "{$item->campo} ({$item->total})";
        })->join(', ');

        return [
            Stat::make('Listings con Bloqueos', $listingsConBloqueos)
                ->description('Listings con al menos un campo bloqueado')
                ->descriptionIcon('heroicon-o-shield-check')
                ->color('success'),

            Stat::make('Campos Bloqueados', $totalCamposBloqueados)
                ->description('Total de bloqueos activos')
                ->descriptionIcon('heroicon-o-lock-closed')
                ->color('warning'),

            Stat::make('Más Bloqueados', $topCampos ?: 'N/A')
                ->description('Campos con más bloqueos')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('info'),
        ];
    }
}

// Para usarlo, añádelo en AdminPanelProvider.php:
// ->widgets([
//     \App\Filament\Widgets\BloqueosStatsWidget::class,
// ])
