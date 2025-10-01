<?php

namespace App\Filament\Resources\BloqueoCampos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class BloqueoCamposTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id_negocio_plataforma')
                    ->label('Listing ID')
                    ->sortable()
                    ->searchable(),

                \Filament\Tables\Columns\TextColumn::make('negocio_nombre')
                    ->label('Negocio')
                    ->getStateUsing(function ($record) {
                        $listing = \App\Models\NegocioPlataforma::find($record->id_negocio_plataforma);
                        return $listing ? $listing->name : 'N/A';
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->whereIn('id_negocio_plataforma', function ($subQuery) use ($search) {
                            $subQuery->select('id')
                                ->from('listing', 'platform')
                                ->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, $direction) {
                        return $query->join('listing', 'bloqueo_campo.id_negocio_plataforma', '=', 'listing.id')
                            ->orderBy('listing.name', $direction);
                    }),

                \Filament\Tables\Columns\TextColumn::make('campo')
                    ->label('Campo')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\IconColumn::make('bloqueado')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('bloqueado')
                    ->options([
                        '1' => 'Bloqueados',
                        '0' => 'Desbloqueados',
                    ])
                    ->label('Estado'),

                \Filament\Tables\Filters\SelectFilter::make('campo')
                    ->options([
                        'name' => 'Nombre',
                        'address' => 'Dirección',
                        'phone' => 'Teléfono',
                        'email' => 'Email',
                        'website' => 'Sitio Web',
                        'description' => 'Descripción',
                        'photos' => 'Fotos',
                        'categories' => 'Categorías',
                        'listing_thumbnail' => 'Miniatura',
                        'listing_cover' => 'Portada',
                    ])
                    ->label('Campo'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('toggle')
                    ->label(fn($record) => $record->bloqueado ? 'Desbloquear' : 'Bloquear')
                    ->icon(fn($record) => $record->bloqueado ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                    ->color(fn($record) => $record->bloqueado ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->bloqueado = !$record->bloqueado;
                        $record->save();

                        \Filament\Notifications\Notification::make()
                            ->title($record->bloqueado ? '🔒 Campo bloqueado' : '🔓 Campo desbloqueado')
                            ->body("El campo '{$record->campo}' ahora está " . ($record->bloqueado ? 'protegido' : 'sincronizable'))
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('ver_listing')
                    ->label('Ver Listing')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn($record) => route('filament.admin.resources.lugar-vinculados.index', [
                        'tableFilters[id_negocio_plataforma][value]' => $record->id_negocio_plataforma
                    ])),
            ])
            ->toolbarActions([
                \Filament\Actions\Action::make('gestionar_por_listing')
                    ->label('Gestionar por Listing')
                    ->icon('heroicon-o-cog')
                    ->url(route('filament.admin.resources.bloqueo-campos.gestionar')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->groups([
                \Filament\Tables\Grouping\Group::make('id_negocio_plataforma')
                    ->label('Por Listing')
                    ->collapsible(),
            ]);
    }
}
