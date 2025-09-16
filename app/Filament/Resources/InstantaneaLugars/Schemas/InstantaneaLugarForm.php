<?php

namespace App\Filament\Resources\InstantaneaLugars\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;

class InstantaneaLugarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id_lugar')
                    ->label('Place ID')
                    ->disabled()
                    ->dehydrated(false), // no enviar al guardar

                Textarea::make('carga')
                    ->label('Payload (JSON)')
                    ->rows(12)
                    ->autosize()
                    ->disabled()
                    ->dehydrated(false),

                DateTimePicker::make('fecha_fetched')
                    ->label('Capturado')
                    ->disabled()
                    ->dehydrated(false),

                // Si quieres permitir EXTENDER el TTL, deja este editable:
                DateTimePicker::make('fecha_expiracion_ttl')
                    ->label('Expira (TTL)')
                    ->helperText('Puedes extender el TTL para reusar este snapshot en la UI.'),
            ]);
    }
}
