<?php

namespace App\Filament\Resources\InstantaneaLugars\Pages;

use App\Filament\Resources\InstantaneaLugars\InstantaneaLugarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstantaneaLugars extends ListRecords
{
    protected static string $resource = InstantaneaLugarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
