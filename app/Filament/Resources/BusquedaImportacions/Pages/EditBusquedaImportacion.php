<?php

namespace App\Filament\Resources\BusquedaImportacions\Pages;

use App\Filament\Resources\BusquedaImportacions\BusquedaImportacionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBusquedaImportacion extends EditRecord
{
    protected static string $resource = BusquedaImportacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

     protected function mutateFormDataBeforeSave(array $data): array
    {
        // Actualizar search_key si cambian nombre o ciudad
        $data['search_key'] = $data['ciudad'] ?
            "{$data['nombre']}, {$data['ciudad']}" :
            $data['nombre'];

        return $data;
    }
}
