<?php

namespace App\Filament\Resources\LugarVinculados\Pages;

use App\Filament\Resources\LugarVinculados\LugarVinculadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLugarVinculados extends ListRecords
{
    protected static string $resource = LugarVinculadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
