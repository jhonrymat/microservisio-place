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

                // Acción: Vincular a Listing
                Action::make('vincular')
                    ->label('Vincular a Listing')
                    ->icon('heroicon-o-link')
                    ->form([
                        TextInput::make('id_listing')
                            ->label('ID en listing (platform)')->numeric()->required(),
                        Toggle::make('descargar_fotos')
                            ->label('Descargar fotos al confirmar')->default(true),
                        TextInput::make('max_fotos')
                            ->label('Máx. fotos (0 = todas)')->numeric()->default(0),
                        Toggle::make('limpiar_lote')
                            ->label('Eliminar los demás resultados de este lote')
                            ->default(true), // ← por defecto SÍ limpia
                    ])
                    ->action(function (array $data, $record) {
                        $svc = app(ImportarNegociosService::class);

                        // 1) Vincular + Detalles + Sincronizar
                        $svc->vincularConPlataforma((int) $data['id_listing'], $record->id_lugar, 0.95, true);
                        $det = $svc->obtenerDetalles($record->id_lugar);
                        $map = app(MapeoPlacesAListingService::class)->mapear($det);
                        app(SincronizarListingService::class)->aplicar((int) $data['id_listing'], $map, 'places_sync');

                        // 2) (Opcional) Fotos del elegido
                        if (!empty($data['descargar_fotos'])) {
                            if ((int) $data['max_fotos'] > 0 && !empty($det['photos'])) {
                                $det['photos'] = array_slice($det['photos'], 0, (int) $data['max_fotos']);
                            }
                            app(FotosService::class)->importarFotosDeLugarSeleccionado(
                                $det,
                                [['label' => 'thumb', 'w' => 400], ['label' => 'cover', 'w' => 1200]]
                            );
                        }

                        // 3) LIMPIAR LOTE (borra los demás candidatos y sus fotos)
                        if (!empty($data['limpiar_lote']) && $record->batch_token) {
                            DB::transaction(function () use ($record) {
                                // place_ids del mismo lote excepto el elegido
                                $otros = InstantaneaLugar::where('batch_token', $record->batch_token)
                                    ->where('id_lugar', '<>', $record->id_lugar)
                                    ->pluck('id_lugar');

                                if ($otros->isNotEmpty()) {
                                    // borrar fotos de esos place_ids
                                    FotoLocal::whereIn('place_id', $otros)->delete();
                                    // borrar snapshots
                                    InstantaneaLugar::whereIn('id_lugar', $otros)->delete();
                                }
                            });
                        }

                        Notification::make()
                            ->title('Vinculado y sincronizado')
                            ->body("Listing {$data['id_listing']} actualizado desde {$record->id_lugar}")
                            ->success()
                            ->send();
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


            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha_fetched', 'desc');
    }
}
