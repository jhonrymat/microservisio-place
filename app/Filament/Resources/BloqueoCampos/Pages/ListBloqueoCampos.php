<?php

// app/Filament/Resources/BloqueoCampos/Pages/ListBloqueoCampos.php
namespace App\Filament\Resources\BloqueoCampos\Pages;

use App\Filament\Resources\BloqueoCampos\BloqueoCampoResource;
use Filament\Resources\Pages\ListRecords;

class ListBloqueoCampos extends ListRecords
{
    protected static string $resource = BloqueoCampoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('gestionar_por_listing')
                ->label('Gestionar por Listing')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('primary')
                ->url(route('filament.admin.resources.bloqueo-campos.gestionar')),
        ];
    }
}
