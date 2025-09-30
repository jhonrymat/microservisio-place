<?php

namespace App\Filament\Resources\BusquedaImportacions\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class BusquedaImportacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de Búsqueda')
                    ->description('Configura los parámetros para buscar negocios en Google Places')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('nombre')
                                    ->label('Nombre del Negocio')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: Hotel Italia')
                                    ->helperText('Nombre del establecimiento a buscar'),

                                TextInput::make('ciudad')
                                    ->label('Ciudad')
                                    ->maxLength(255)
                                    ->placeholder('Ej: Acacías, Meta, Colombia')
                                    ->helperText('Ciudad o ubicación (opcional pero recomendado)'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('lat')
                                    ->label('Latitud')
                                    ->numeric()
                                    ->step(0.0000001)
                                    ->placeholder('Ej: 3.986')
                                    ->helperText('Coordenada para mejorar precisión'),

                                TextInput::make('lng')
                                    ->label('Longitud')
                                    ->numeric()
                                    ->step(0.0000001)
                                    ->placeholder('Ej: -73.765')
                                    ->helperText('Coordenada para mejorar precisión'),

                                TextInput::make('page_size')
                                    ->label('Cantidad de Resultados')
                                    ->numeric()
                                    ->default(15)
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->helperText('Máximo 20 resultados'),
                            ]),

                        Toggle::make('usar_restriccion')
                            ->label('Usar Restricción Estricta')
                            ->helperText('Si está activado, restringe los resultados al área de las coordenadas. Si no, solo las usa como sesgo.')
                            ->default(false),
                    ]),

                Section::make('Estado')
                    ->description('Información del estado de la búsqueda')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('estado')
                                    ->label('Estado')
                                    ->options([
                                        'pendiente' => 'Pendiente',
                                        'ejecutando' => 'Ejecutando',
                                        'completado' => 'Completado',
                                        'fallido' => 'Fallido',
                                    ])
                                    ->default('pendiente')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('candidatos_encontrados')
                                    ->label('Candidatos Encontrados')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        TextInput::make('batch_token')
                            ->label('Token del Lote')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Se genera automáticamente al ejecutar la búsqueda'),

                        TextInput::make('search_key')
                            ->label('Clave de Búsqueda')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Se genera automáticamente combinando nombre y ciudad'),
                    ])
                    ->hiddenOn('create'),
            ]);
    }
}
