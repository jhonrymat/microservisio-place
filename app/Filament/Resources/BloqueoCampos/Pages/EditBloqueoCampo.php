<?php

namespace App\Filament\Resources\BloqueoCampos\Pages;

use App\Filament\Resources\BloqueoCampos\BloqueoCampoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBloqueoCampo extends EditRecord
{
    protected static string $resource = BloqueoCampoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
