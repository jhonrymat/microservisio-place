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
                TextColumn::make('updated_at')->dateTime()->since(), // “hace 5 min”
            ])
            ->filters([
               
            ])
            ->recordActions([
                Action::make('sync')
                    ->label('Sincronizar datos')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($record) {
                        $svc = app(\App\Services\ImportarNegociosService::class);
                        $det = $svc->obtenerDetalles($record->id_lugar);
                        $map = app(\App\Services\MapeoPlacesAListingService::class)->mapear($det);
                        app(\App\Services\SincronizarListingService::class)
                            ->aplicar((int) $record->id_negocio_plataforma, $map, 'places_sync');

                        \Filament\Notifications\Notification::make()
                            ->title('Sincronización completada')->success()->send();
                    }),

                Action::make('sync_fotos')
                    ->label('Sincronizar + fotos')
                    ->icon('heroicon-o-photo')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('max_fotos')
                            ->numeric()->default(0)->label('Máx. fotos (0 = todas)'),
                    ])
                    ->action(function (array $data, $record) {
                        $svc = app(\App\Services\ImportarNegociosService::class);
                        $det = $svc->obtenerDetalles($record->id_lugar);

                        if ((int) ($data['max_fotos'] ?? 0) > 0 && !empty($det['photos'])) {
                            $det['photos'] = array_slice($det['photos'], 0, (int) $data['max_fotos']);
                        }

                        // Datos
                        $map = app(\App\Services\MapeoPlacesAListingService::class)->mapear($det);
                        app(\App\Services\SincronizarListingService::class)
                            ->aplicar((int) $record->id_negocio_plataforma, $map, 'places_sync');

                        // Fotos (thumb + cover)
                        $fotos = app(\App\Services\FotosService::class)->importarFotosDeLugarSeleccionado(
                            $det,
                            [['label' => 'thumb', 'w' => 400], ['label' => 'cover', 'w' => 1200]]
                        );

                        // Asignar thumbnail/cover si no están bloqueados
                        $thumb = collect($fotos)->firstWhere('size_label', 'thumb');
                        $cover = collect($fotos)->firstWhere('size_label', 'cover');

                        $locks = \App\Models\BloqueoCampo::where('id_negocio_plataforma', (int) $record->id_negocio_plataforma)
                            ->pluck('bloqueado', 'campo');

                        $updates = ['date_modified' => time()];
                        if ($thumb && !($locks['listing_thumbnail'] ?? false)) {
                            $updates['listing_thumbnail'] = route('media.local', $thumb->id);
                        }
                        if ($cover && !($locks['listing_cover'] ?? false)) {
                            $updates['listing_cover'] = route('media.local', $cover->id);
                        }
                        if (count($updates) > 1) {
                            \Illuminate\Support\Facades\DB::connection('platform')->table('listing')
                                ->where('id', (int) $record->id_negocio_plataforma)
                                ->update($updates);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Sincronización + fotos completada')->success()->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
