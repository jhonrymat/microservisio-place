<?php

namespace App\Filament\Resources\InstantaneaLugars\Tables;

use Filament\Tables;
use App\Models\FotoLocal;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Services\FotosService;
use App\Models\InstantaneaLugar;
use Illuminate\Support\Facades\DB;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use App\Services\ImportarNegociosService;
use App\Services\SincronizarListingService;
use App\Services\MapeoPlacesAListingService;

class InstantaneaLugarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Miniatura (1er thumb en fotos_locales)
                ImageColumn::make('thumb')
                    ->label('Foto')
                    ->getStateUsing(function ($record) {
                        $foto = FotoLocal::where('place_id', $record->id_lugar)
                            ->where('size_label', 'thumb')
                            ->latest('fetched_at')
                            ->first();
                        return $foto ? route('media.local', $foto->id) : null;
                    })
                    ->circular()
                    ->height(48),

                TextColumn::make('id_lugar')
                    ->label('Place ID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->getStateUsing(fn($record) => data_get(json_decode($record->carga, true), 'displayName.text'))
                    ->sortable()
                    ->wrap(),

                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->getStateUsing(fn($record) => data_get(json_decode($record->carga, true), 'formattedAddress'))
                    ->limit(40)
                    ->wrap(),

                TextColumn::make('busquedaImportacion.nombre')
                    ->label('Búsqueda Origen')
                    ->searchable()
                    ->limit(25)
                    ->placeholder('—')
                    ->description(fn($record) => $record->busquedaImportacion?->ciudad),

                TextColumn::make('busquedaImportacion.estado')
                    ->label('Estado Búsqueda')
                    ->badge()
                    ->colors([
                        'warning' => 'pendiente',
                        'info' => 'ejecutando',
                        'success' => 'completado',
                        'danger' => 'fallido',
                    ])
                    ->placeholder('—'),

                TextColumn::make('fecha_fetched')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('fecha_expiracion_ttl')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('batch_token')
                    ->label('Lote')
                    ->form([
                        TextInput::make('batch')
                            ->placeholder('pe. 3c1c1a3a-...'),
                    ])
                    ->query(function ($query, array $data) {
                        $batch = $data['batch'] ?? null;
                        if (filled($batch)) {
                            $query->where('batch_token', $batch);
                        }
                        return $query;
                    }),

                \Filament\Tables\Filters\Filter::make('search_key')
                    ->label('Búsqueda')
                    ->form([
                        TextInput::make('q')
                            ->placeholder('Panadería El Trigal | Acacías, Meta, Colombia'),
                    ])
                    ->query(function ($query, array $data) {
                        $q = $data['q'] ?? null;
                        if (filled($q)) {
                            $query->where('search_key', 'like', '%' . $q . '%');
                        }
                        return $query;
                    }),

                // En los filtros, añadir:
                \Filament\Tables\Filters\Filter::make('busqueda_origen')
                    ->label('Búsqueda de Origen')
                    ->form([
                        \Filament\Forms\Components\Select::make('busqueda_id')
                            ->label('Búsqueda')
                            ->options(function () {
                                return \App\Models\BusquedaImportacion::query()
                                    ->selectRaw('id, CONCAT(nombre, " (", COALESCE(ciudad, "Sin ciudad"), ")") as display_name')
                                    ->orderBy('created_at', 'desc')
                                    ->limit(50)
                                    ->pluck('display_name', 'id');
                            })
                            ->searchable(),
                    ])
                    ->query(function ($query, array $data) {
                        $busquedaId = $data['busqueda_id'] ?? null;
                        if (filled($busquedaId)) {
                            $busqueda = \App\Models\BusquedaImportacion::find($busquedaId);
                            if ($busqueda && $busqueda->batch_token) {
                                $query->where('batch_token', $busqueda->batch_token);
                            }
                        }
                        return $query;
                    }),

                \Filament\Tables\Filters\Filter::make('sin_vincular')
                    ->label('Sin Vincular')
                    ->toggle()
                    ->query(fn($query) => $query->sinVincular()),

                \Filament\Tables\Filters\Filter::make('vinculados')
                    ->label('Vinculados')
                    ->toggle()
                    ->query(fn($query) => $query->vinculados()),
            ])
            ->recordActions([
                // Acción: Ver JSON
                Action::make('ver_json')
                    ->label('Ver JSON')
                    ->icon('heroicon-o-code-bracket')
                    ->modalHeading('Snapshot JSON')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(function ($record) {
                        $pretty = json_encode(json_decode($record->carga, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        return view('filament.partials.json-pretty', ['json' => $pretty]);
                    }),

                // En tu InstantaneaLugarsTable.php - reemplaza la acción 'vincular'

                Action::make('vincular')
                    ->label('Vincular a Listing')
                    ->icon('heroicon-o-link')
                    ->form([
                        TextInput::make('id_listing')
                            ->label('ID en listing (platform)')
                            ->numeric()
                            ->required(),
                        Toggle::make('descargar_fotos')
                            ->label('Descargar fotos en alta calidad')
                            ->default(true)
                            ->helperText('Se eliminarán las fotos de baja calidad y se descargarán en alta resolución'),
                        TextInput::make('max_fotos')
                            ->label('Máx. fotos (0 = todas)')
                            ->numeric()
                            ->default(0)
                            ->helperText('Cantidad máxima de fotos a descargar en alta calidad'),
                        Toggle::make('limpiar_lote')
                            ->label('Eliminar candidatos descartados')
                            ->default(true)
                            ->helperText('Elimina los demás resultados de esta búsqueda'),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            $svc = app(ImportarNegociosService::class);
                            $fotosService = app(FotosService::class);

                            // 1) LIMPIAR TODAS LAS FOTOS DEL LOTE (incluyendo las del seleccionado)
                            if (!empty($data['limpiar_lote']) && $record->batch_token) {
                                DB::transaction(function () use ($record, $fotosService) {
                                    // Obtener TODOS los place_ids del lote (incluyendo el seleccionado)
                                    $todosLosPlaceIds = InstantaneaLugar::where('batch_token', $record->batch_token)
                                        ->pluck('id_lugar');

                                    if ($todosLosPlaceIds->isNotEmpty()) {
                                        // BORRAR TODAS las fotos del lote (incluyendo las del seleccionado)
                                        $fotosService->eliminarFotosDeVariosLugares($todosLosPlaceIds->toArray());

                                        \Log::info('Fotos de baja calidad eliminadas', [
                                            'batch_token' => $record->batch_token,
                                            'places_eliminados' => $todosLosPlaceIds->count(),
                                        ]);
                                    }

                                    // Borrar snapshots de los candidatos NO seleccionados
                                    $otros = InstantaneaLugar::where('batch_token', $record->batch_token)
                                        ->where('id_lugar', '<>', $record->id_lugar)
                                        ->pluck('id_lugar');

                                    if ($otros->isNotEmpty()) {
                                        InstantaneaLugar::whereIn('id_lugar', $otros)->delete();

                                        \Log::info('Candidatos no seleccionados eliminados', [
                                            'batch_token' => $record->batch_token,
                                            'candidatos_eliminados' => $otros->count(),
                                        ]);
                                    }
                                });
                            }

                            // 2) Vincular + Obtener detalles + Sincronizar
                            $svc->vincularConPlataforma((int) $data['id_listing'], $record->id_lugar, 0.95, true);
                            $det = $svc->obtenerDetalles($record->id_lugar);
                            $map = app(MapeoPlacesAListingService::class)->mapear($det);
                            app(SincronizarListingService::class)->aplicar((int) $data['id_listing'], $map, 'places_sync');

                            // 3) Descargar fotos en ALTA CALIDAD del lugar seleccionado
                            if (!empty($data['descargar_fotos'])) {
                                // Limitar cantidad si se especifica
                                if ((int) $data['max_fotos'] > 0 && !empty($det['photos'])) {
                                    $det['photos'] = array_slice($det['photos'], 0, (int) $data['max_fotos']);
                                }

                                // Descargar en ALTA calidad (thumb 400, cover 1200, full 2048)
                                $fotos = $fotosService->importarFotosDeLugarSeleccionado(
                                    $det,
                                    [
                                        ['label' => 'thumb', 'w' => 400],
                                        ['label' => 'cover', 'w' => 1200],
                                        ['label' => 'full', 'w' => 2048],  // 👈 Alta calidad
                                    ]
                                );

                                \Log::info('Fotos de alta calidad descargadas', [
                                    'place_id' => $record->id_lugar,
                                    'total_fotos' => count($fotos),
                                    'fotos_originales' => count($det['photos'] ?? []),
                                ]);

                                // Actualizar thumbnail y cover en el listing
                                $thumb = collect($fotos)->firstWhere('size_label', 'thumb');
                                $cover = collect($fotos)->firstWhere('size_label', 'cover');

                                $locks = \App\Models\BloqueoCampo::where('id_negocio_plataforma', (int) $data['id_listing'])
                                    ->pluck('bloqueado', 'campo');

                                $updates = ['date_modified' => time()];

                                if ($thumb && !($locks['listing_thumbnail'] ?? false)) {
                                    $updates['listing_thumbnail'] = route('media.local', $thumb->id);
                                }
                                if ($cover && !($locks['listing_cover'] ?? false)) {
                                    $updates['listing_cover'] = route('media.local', $cover->id);
                                }

                                if (count($updates) > 1) {
                                    DB::connection('platform')->table('listing')
                                        ->where('id', (int) $data['id_listing'])
                                        ->update($updates);
                                }
                            }

                            Notification::make()
                                ->title('✅ Vinculación completada')
                                ->body("Listing {$data['id_listing']} actualizado con fotos de alta calidad")
                                ->success()
                                ->duration(5000)
                                ->send();

                        } catch (\Exception $e) {
                            \Log::error('Error al vincular y descargar fotos', [
                                'error' => $e->getMessage(),
                                'place_id' => $record->id_lugar,
                                'listing_id' => $data['id_listing'] ?? null,
                            ]);

                            Notification::make()
                                ->title('❌ Error en la vinculación')
                                ->body($e->getMessage())
                                ->danger()
                                ->duration(10000)
                                ->send();
                        }
                    }),

                Action::make('extender_ttl')
                    ->label('Extender TTL +7 días')
                    ->icon('heroicon-o-clock')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->fecha_expiracion_ttl = now()->addDays(7);
                        $record->save();

                        Notification::make()
                            ->title('TTL extendido')
                            ->success()
                            ->send();
                    }),

                Action::make('ver_fotos')
                    ->label('Fotos')
                    ->icon('heroicon-o-photo')
                    ->modalHeading('Fotos del candidato')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('xl')
                    ->modalContent(function ($record) {
                        $fotos = FotoLocal::where('place_id', $record->id_lugar)
                            ->where('size_label', 'thumb')
                            ->orderBy('id')
                            ->limit(12)
                            ->get();

                        return view('filament.partials.grid-fotos', [
                            'fotos' => $fotos,
                        ]);
                    }),

                Action::make('ver_busqueda_origen')
                    ->label('Ver Búsqueda')
                    ->icon('heroicon-o-arrow-up-right')
                    ->color('gray')
                    ->visible(fn($record) => $record->busquedaImportacion)
                    ->url(
                        fn($record) => $record->busquedaImportacion ?
                        route('filament.admin.resources.busqueda-importacions.edit', $record->busquedaImportacion) :
                        null
                    ),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha_fetched', 'desc');
    }
}
