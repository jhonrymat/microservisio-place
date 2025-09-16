<?php

namespace App\Filament\Resources\InstantaneaLugars\Pages;

use App\Filament\Resources\InstantaneaLugars\InstantaneaLugarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstantaneaLugar extends EditRecord
{
    protected static string $resource = InstantaneaLugarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // DeleteAction::make(),
        ];
    }
}
