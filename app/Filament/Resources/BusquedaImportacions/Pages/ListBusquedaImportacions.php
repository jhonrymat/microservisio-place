<?php

namespace App\Filament\Resources\BusquedaImportacions\Pages;

use App\Filament\Resources\BusquedaImportacions\BusquedaImportacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBusquedaImportacions extends ListRecords
{
    protected static string $resource = BusquedaImportacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
