<?php

namespace App\Filament\Resources\LugarVinculados\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LugarVinculadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id_negocio_plataforma')
                    ->required()
                    ->numeric(),
                TextInput::make('id_lugar')
                    ->required(),
                TextInput::make('confianza')
                    ->required()
                    ->numeric(),
                TextInput::make('estrategia_coincidencia')
                    ->required(),
                Select::make('estado')
                    ->options([
            'vinculado' => 'Vinculado',
            'necesita_revisión' => 'Necesita revisión',
            'inválido' => 'Inválido',
        ])
                    ->required(),
                DateTimePicker::make('ultima_verificacion'),
            ]);
    }
}
