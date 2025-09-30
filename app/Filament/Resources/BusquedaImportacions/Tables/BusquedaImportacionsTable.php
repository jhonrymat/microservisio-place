<?php

namespace App\Filament\Resources\BusquedaImportacions\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use App\Services\FotosService;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use App\Services\ImportarNegociosService;

class BusquedaImportacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('ciudad')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'pendiente',
                        'info' => 'ejecutando',
                        'success' => 'completado',
                        'danger' => 'fallido',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pendiente',
                        'heroicon-o-arrow-path' => 'ejecutando',
                        'heroicon-o-check-circle' => 'completado',
                        'heroicon-o-x-circle' => 'fallido',
                    ]),

                TextColumn::make('candidatos_encontrados')
                    ->label('Candidatos')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('page_size')
                    ->label('Tamaño')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('usar_restriccion')
                    ->label('Restricción'),
                    // ->boolean()
                    // ->trueIcon('heroicon-o-check-circle')
                    // ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('fecha_ejecutada')
                    ->label('Ejecutada')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('No ejecutada'),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'ejecutando' => 'Ejecutando',
                        'completado' => 'Completado',
                        'fallido' => 'Fallido',
                    ]),
            ])
            ->recordActions([
                // Acción principal: Ejecutar búsqueda
                Action::make('ejecutar_busqueda')
                    ->label('Ejecutar Búsqueda')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn($record) => $record->estado === 'pendiente')
                    ->requiresConfirmation()
                    ->modalHeading('Ejecutar Búsqueda de Importación')
                    ->modalDescription(fn($record) => "¿Estás seguro de que quieres ejecutar la búsqueda para '{$record->nombre}' en {$record->ciudad}?")
                    ->action(function ($record) {
                        try {
                            // Cambiar estado a ejecutando
                            $record->update([
                                'estado' => 'ejecutando',
                                'fecha_ejecutada' => now(),
                                'mensaje_error' => null,
                            ]);

                            // Generar tokens si no existen
                            $batchToken = $record->generarBatchToken();
                            $searchKey = $record->generarSearchKey();

                            $record->update([
                                'batch_token' => $batchToken,
                                'search_key' => $searchKey,
                            ]);

                            // Ejecutar la búsqueda
                            $importarService = app(ImportarNegociosService::class);

                            $candidatos = $importarService->importarPorNombre(
                                $record->nombre,
                                $record->ciudad,
                                $record->lat,
                                $record->lng,
                                $batchToken,
                                $searchKey,
                                $record->page_size,
                                $record->usar_restriccion
                            );

                            // Prefetch de miniaturas
                            if (!empty($candidatos)) {
                                app(FotosService::class)->prefetchMiniaturasCandidatos($candidatos, 3, 320);
                            }

                            // Actualizar estado a completado
                            $record->update([
                                'estado' => 'completado',
                                'candidatos_encontrados' => count($candidatos),
                            ]);

                            Notification::make()
                                ->title('Búsqueda completada')
                                ->body("Se encontraron " . count($candidatos) . " candidatos.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            // Marcar como fallido
                            $record->update([
                                'estado' => 'fallido',
                                'mensaje_error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Error en la búsqueda')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Ver resultados
                Action::make('ver_resultados')
                    ->label('Ver Resultados')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn($record) => $record->estado === 'completado' && $record->candidatos_encontrados > 0)
                    ->url(fn($record) => route('filament.admin.resources.instantanea-lugars.index', [
                        'tableFilters[batch_token][batch]' => $record->batch_token
                    ])),

                // Repetir búsqueda
                Action::make('repetir_busqueda')
                    ->label('Repetir')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn($record) => in_array($record->estado, ['completado', 'fallido']))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'estado' => 'pendiente',
                            'batch_token' => null,
                            'candidatos_encontrados' => null,
                            'mensaje_error' => null,
                            'fecha_ejecutada' => null,
                        ]);

                        Notification::make()
                            ->title('Búsqueda reiniciada')
                            ->body('Puedes ejecutar la búsqueda nuevamente.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
