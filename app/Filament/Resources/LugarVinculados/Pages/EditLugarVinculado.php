<?php

namespace App\Filament\Resources\LugarVinculados\Pages;

use App\Filament\Resources\LugarVinculados\LugarVinculadoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLugarVinculado extends EditRecord
{
    protected static string $resource = LugarVinculadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
