<?php

namespace App\Filament\Resources\LugarVinculados\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class LugarVinculadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id_negocio_plataforma')->label('Listing ID')->sortable()->searchable(),
                TextColumn::make('id_lugar')->label('Place ID')->copyable()->searchable(),
                TextColumn::make('confianza')->label('Conf.')->numeric(2)->sortable(),
                TextColumn::make('estrategia_coincidencia')->label('Estrategia'),
                TextColumn::make('ultima_verificacion')->label('Verificado')->dateTime()->sortable(),
                TextColumn::make('updated_at')->dateTime()->since(),
            ])
            ->filters([])
            ->recordActions([
                // ========== SINCRONIZAR SOLO DATOS ==========
                Action::make('sync')
                    ->label('Sincronizar datos')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar datos desde Google Places')
                    ->modalDescription('Se actualizarán nombre, dirección, teléfono, horarios, etc. Las fotos NO se modificarán.')
                    ->modalSubmitActionLabel('Sincronizar')
                    ->action(function ($record) {
                        try {
                            $svc = app(\App\Services\ImportarNegociosService::class);
                            $mapeoService = app(\App\Services\MapeoPlacesAListingService::class);

                            // Obtener detalles actualizados
                            $det = $svc->obtenerDetalles($record->id_lugar);

                            // Mapear datos
                            $map = $mapeoService->mapear($det);

                            // Sincronizar en listing
                            app(\App\Services\SincronizarListingService::class)
                                ->aplicar((int) $record->id_negocio_plataforma, $map, 'places_sync');

                            // Guardar horarios en time_configuration
                            try {
                                $mapeoService->guardarHorarios((int) $record->id_negocio_plataforma, $det);
                                \Log::info('Horarios sincronizados', [
                                    'listing_id' => $record->id_negocio_plataforma
                                ]);
                            } catch (\Exception $e) {
                                \Log::error('Error al sincronizar horarios', [
                                    'listing_id' => $record->id_negocio_plataforma,
                                    'error' => $e->getMessage()
                                ]);
                            }

                            // Actualizar última verificación
                            $record->ultima_verificacion = now();
                            $record->save();

                            \Filament\Notifications\Notification::make()
                                ->title('✅ Sincronización completada')
                                ->body('Datos y horarios actualizados desde Google Places')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Log::error('Error en sincronización', [
                                'lugar_vinculado_id' => $record->id,
                                'error' => $e->getMessage()
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('❌ Error en sincronización')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ========== SINCRONIZAR DATOS + FOTOS ==========
                Action::make('sync_fotos')
                    ->label('Sincronizar + fotos')
                    ->icon('heroicon-o-photo')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('max_fotos')
                            ->label('Máximo de fotos a descargar')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(50)
                            ->helperText('0 = descargar todas las fotos disponibles'),

                        \Filament\Forms\Components\Toggle::make('reemplazar_fotos')
                            ->label('Reemplazar fotos existentes')
                            ->default(false)
                            ->helperText('Si está desactivado, solo descargará fotos si no existen')
                            ->inline(false),
                    ])
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Sincronizar')
                    ->action(function (array $data, $record) {
                        try {
                            $svc = app(\App\Services\ImportarNegociosService::class);
                            $fotosService = app(\App\Services\FotosService::class);
                            $mapeoService = app(\App\Services\MapeoPlacesAListingService::class);

                            // Obtener detalles
                            $det = $svc->obtenerDetalles($record->id_lugar);

                            // 1) SINCRONIZAR DATOS Y HORARIOS
                            $map = $mapeoService->mapear($det);
                            app(\App\Services\SincronizarListingService::class)
                                ->aplicar((int) $record->id_negocio_plataforma, $map, 'places_sync');

                            $mapeoService->guardarHorarios((int) $record->id_negocio_plataforma, $det);

                            // 2) GESTIONAR FOTOS
                            $reemplazar = $data['reemplazar_fotos'] ?? false;
                            $fotosExistentes = \App\Models\FotoLocal::where('place_id', $record->id_lugar)
                                ->whereIn('size_label', ['thumb', 'cover', 'full'])
                                ->count();

                            if ($reemplazar && $fotosExistentes > 0) {
                                // Eliminar fotos existentes
                                $fotosService->eliminarFotosDeUnLugar($record->id_lugar);
                                \Log::info('Fotos reemplazadas', [
                                    'place_id' => $record->id_lugar,
                                    'eliminadas' => $fotosExistentes
                                ]);
                            } elseif ($fotosExistentes > 0 && !$reemplazar) {
                                // Ya existen fotos y no quiere reemplazar
                                \Filament\Notifications\Notification::make()
                                    ->title('ℹ️ Fotos no descargadas')
                                    ->body("Ya existen {$fotosExistentes} fotos. Activa 'Reemplazar fotos' si quieres descargar nuevas.")
                                    ->info()
                                    ->send();

                                // Actualizar verificación y salir
                                $record->ultima_verificacion = now();
                                $record->save();
                                return;
                            }

                            // 3) DESCARGAR FOTOS (si no existen o si se reemplazaron)
                            if (!empty($det['photos'])) {
                                $maxFotos = (int) ($data['max_fotos'] ?? 0);
                                if ($maxFotos > 0) {
                                    $det['photos'] = array_slice($det['photos'], 0, $maxFotos);
                                }

                                // Descargar en 3 resoluciones
                                $fotos = $fotosService->importarFotosDeLugarSeleccionado(
                                    $det,
                                    [
                                        ['label' => 'thumb', 'w' => 400],
                                        ['label' => 'cover', 'w' => 1200],
                                        ['label' => 'full', 'w' => 2048],
                                    ]
                                );

                                \Log::info('Fotos descargadas en sincronización', [
                                    'place_id' => $record->id_lugar,
                                    'cantidad_fotos_originales' => count($det['photos']),
                                    'cantidad_registros' => count($fotos), // 3x por cada foto
                                ]);

                                // 4) ACTUALIZAR THUMBNAIL Y COVER EN LISTING
                                self::actualizarImagenesListing(
                                    (int) $record->id_negocio_plataforma,
                                    $record->id_lugar,
                                    $fotosService
                                );

                                $cantidadDescargadas = count($det['photos']);

                                \Filament\Notifications\Notification::make()
                                    ->title('✅ Sincronización completa')
                                    ->body("Datos actualizados y {$cantidadDescargadas} fotos descargadas (en 3 resoluciones)")
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('⚠️ Sin fotos')
                                    ->body('Datos actualizados, pero no hay fotos disponibles en Google Places')
                                    ->warning()
                                    ->send();
                            }

                            // Actualizar última verificación
                            $record->ultima_verificacion = now();
                            $record->save();

                        } catch (\Exception $e) {
                            \Log::error('Error en sync_fotos', [
                                'lugar_vinculado_id' => $record->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('❌ Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ========== SOLO REEMPLAZAR FOTOS ==========
                Action::make('reemplazar_fotos')
                    ->label('Reemplazar fotos')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reemplazar todas las fotos?')
                    ->modalDescription('Se eliminarán las fotos actuales y se descargarán nuevas desde Google Places.')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('max_fotos')
                            ->label('Máximo de fotos')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 = todas las disponibles'),
                    ])
                    ->modalSubmitActionLabel('Reemplazar')
                    ->visible(function ($record) {
                        // Solo mostrar si hay fotos existentes
                        return \App\Models\FotoLocal::where('place_id', $record->id_lugar)
                            ->exists();
                    })
                    ->action(function (array $data, $record) {
                        try {
                            $fotosService = app(\App\Services\FotosService::class);
                            $svc = app(\App\Services\ImportarNegociosService::class);

                            // Eliminar fotos existentes
                            $eliminadas = $fotosService->eliminarFotosDeUnLugar($record->id_lugar);

                            // Descargar nuevas
                            $det = $svc->obtenerDetalles($record->id_lugar);

                            if (empty($det['photos'])) {
                                throw new \Exception('No hay fotos disponibles en Google Places');
                            }

                            $maxFotos = (int) ($data['max_fotos'] ?? 0);
                            if ($maxFotos > 0) {
                                $det['photos'] = array_slice($det['photos'], 0, $maxFotos);
                            }

                            $fotos = $fotosService->importarFotosDeLugarSeleccionado(
                                $det,
                                [
                                    ['label' => 'thumb', 'w' => 400],
                                    ['label' => 'cover', 'w' => 1200],
                                    ['label' => 'full', 'w' => 2048],
                                ]
                            );

                            // Actualizar thumbnail y cover
                            self::actualizarImagenesListing(
                                (int) $record->id_negocio_plataforma,
                                $record->id_lugar,
                                $fotosService
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('✅ Fotos reemplazadas')
                                ->body("Eliminadas: {$eliminadas} | Descargadas: " . count($det['photos']))
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * Actualiza listing_thumbnail y listing_cover respetando bloqueos
     */
    protected static function actualizarImagenesListing(
        int $listingId,
        string $placeId,
        \App\Services\FotosService $fotosService
    ): void {
        // Obtener bloqueos
        $locks = \App\Models\BloqueoCampo::where('id_negocio_plataforma', $listingId)
            ->pluck('bloqueado', 'campo');

        $updates = ['date_modified' => time()];

        // Thumbnail (si no está bloqueado)
        if (!($locks['listing_thumbnail'] ?? false)) {
            $thumbUrls = $fotosService->construirUrlsFotos($placeId, 'thumb', 1);
            if (!empty($thumbUrls)) {
                $updates['listing_thumbnail'] = $thumbUrls[0];
            }
        }

        // Cover (si no está bloqueado)
        if (!($locks['listing_cover'] ?? false)) {
            $coverUrls = $fotosService->construirUrlsFotos($placeId, 'cover', 1);
            if (!empty($coverUrls)) {
                $updates['listing_cover'] = $coverUrls[0];
            }
        }

        // Actualizar si hay cambios
        if (count($updates) > 1) {
            \Illuminate\Support\Facades\DB::connection('platform')
                ->table('listing')
                ->where('id', $listingId)
                ->update($updates);

            \Log::info('Imágenes de listing actualizadas', [
                'listing_id' => $listingId,
                'campos' => array_keys($updates)
            ]);
        }
    }
}
