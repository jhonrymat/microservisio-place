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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use App\Forms\Components\ImageSelector;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use App\Services\ImportarNegociosService;
use App\Services\SincronizarListingService;
use Filament\Forms\Components\CheckboxList;
use App\Services\MapeoPlacesAListingService;
use Filament\Schemas\Components\Wizard\Step;

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
                        Select::make('busqueda_id')
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
                    ->modalWidth('4xl')
                    ->steps([
                        // ========== PASO 1: SELECCIONAR LISTING Y OPCIONES ==========
                        Step::make('Configuración')
                            ->schema([
                                Select::make('id_listing')
                                    ->label('Buscar Negocio en Plataforma')
                                    ->searchable()
                                    ->required()
                                    ->helperText('Busca por nombre o ID del negocio')
                                    ->placeholder('Escribe para buscar...')
                                    ->getSearchResultsUsing(function (string $search) {
                                        return \App\Models\NegocioPlataforma::query()
                                            ->where(function ($query) use ($search) {
                                                $query->where('name', 'like', "%{$search}%")
                                                    ->orWhere('id', '=', $search);
                                            })
                                            ->where('status', '!=', 'deleted')
                                            ->limit(20)
                                            ->get()
                                            ->mapWithKeys(function ($listing) {
                                                $label = sprintf(
                                                    '#%d - %s%s',
                                                    $listing->id,
                                                    $listing->name,
                                                    $listing->address ? ' | ' . \Illuminate\Support\Str::limit($listing->address, 40) : ''
                                                );
                                                return [$listing->id => $label];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $listing = \App\Models\NegocioPlataforma::find($value);
                                        if (!$listing)
                                            return "Listing #{$value}";
                                        return sprintf('#%d - %s', $listing->id, $listing->name);
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $listing = \App\Models\NegocioPlataforma::find($state);
                                            if ($listing) {
                                                $set('preview_name', $listing->name);
                                                $set('preview_address', $listing->address);
                                                $set('preview_status', $listing->status);
                                            }
                                        }
                                    }),

                                Section::make('📋 Información del Negocio')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('preview_name')->label('Nombre')->disabled()->dehydrated(false),
                                            TextInput::make('preview_status')->label('Estado')->disabled()->dehydrated(false),
                                        ]),
                                        TextInput::make('preview_address')->label('Dirección')->disabled()->dehydrated(false),
                                    ])
                                    ->visible(fn(callable $get) => filled($get('id_listing')))
                                    ->collapsible()
                                    ->collapsed(),

                                Section::make('⚙️ Opciones de Sincronización')
                                    ->schema([
                                        Toggle::make('descargar_fotos')
                                            ->label('Descargar fotos en alta calidad')
                                            ->default(true)
                                            ->inline(false),

                                        TextInput::make('max_fotos')
                                            ->label('Máx. fotos (0 = todas)')
                                            ->numeric()
                                            ->default(0)
                                            ->visible(fn(callable $get) => $get('descargar_fotos')),

                                        Toggle::make('limpiar_lote')
                                            ->label('Eliminar candidatos descartados')
                                            ->default(true)
                                            ->inline(false),

                                        Toggle::make('seleccionar_imagenes_manualmente')
                                            ->label('Seleccionar thumbnail y cover manualmente')
                                            ->default(false)
                                            ->helperText('Si activas esto, en el siguiente paso podrás elegir qué imágenes usar')
                                            ->inline(false)
                                            ->reactive(),
                                    ])
                                    ->collapsible(),

                                Section::make('🔒 Bloqueos Iniciales (Opcional)')
                                    ->schema([
                                        Toggle::make('aplicar_bloqueos_default')
                                            ->label('Aplicar bloqueos por defecto')
                                            ->default(false)
                                            ->inline(false)
                                            ->reactive(),

                                        CheckboxList::make('bloqueos_personalizados')
                                            ->label('O selecciona campos específicos')
                                            ->options([
                                                'name' => 'Nombre',
                                                'address' => 'Dirección',
                                                'phone' => 'Teléfono',
                                                'description' => 'Descripción',
                                                'photos' => 'Fotos',
                                                'listing_thumbnail' => 'Miniatura',
                                                'listing_cover' => 'Portada',
                                            ])
                                            ->columns(3)
                                            ->visible(fn(callable $get) => !$get('aplicar_bloqueos_default')),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ]),

                        // ========== PASO 2: SELECCIONAR IMÁGENES (Condicional) ==========
                        Step::make('Seleccionar Imágenes')
    ->schema([
        \Filament\Forms\Components\Placeholder::make('info_seleccion')
            ->content(new \Illuminate\Support\HtmlString(
                '<div class="bg-blue-50 dark:bg-blue-950 p-4 rounded-lg mb-4">
                    <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
                        📸 Selecciona las Imágenes para el Listing
                    </h3>
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        Haz clic en la imagen que desees usar. Si no seleccionas ninguna, se usará la primera automáticamente.
                    </p>
                </div>'
            )),

        // SELECTOR DE THUMBNAIL
        Section::make('🖼️ Thumbnail (Miniatura)')
            ->description('Imagen pequeña que se muestra en listados y tarjetas (400px)')
            ->schema([
                \Filament\Forms\Components\Hidden::make('thumbnail_id'),

                \Filament\Forms\Components\ViewField::make('thumbnail_selector')
                    ->view('filament.forms.image-grid-selector')
                    ->viewData(function ($record) {
                        if (!$record) return ['images' => [], 'field' => 'thumbnail_id'];

                        $fotos = \App\Models\FotoLocal::where('place_id', $record->id_lugar)
                            ->where('size_label', 'thumb')
                            ->orderBy('id')
                            ->limit(12)
                            ->get();

                        return [
                            'images' => $fotos->map(fn($f) => [
                                'id' => $f->id,
                                'url' => route('media.local', $f->id),
                                'width' => $f->width,
                                'height' => $f->height,
                                'author' => $f->author_name,
                            ])->toArray(),
                            'field' => 'thumbnail_id',
                            'columns' => 4,
                        ];
                    }),
            ])
            ->collapsible(),

        // SELECTOR DE COVER
        Section::make('🎨 Cover (Portada)')
            ->description('Imagen grande que se muestra en la cabecera del listing (1200px)')
            ->schema([
                \Filament\Forms\Components\Hidden::make('cover_id'),

                \Filament\Forms\Components\ViewField::make('cover_selector')
                    ->view('filament.forms.image-grid-selector')
                    ->viewData(function ($record) {
                        if (!$record) return ['images' => [], 'field' => 'cover_id'];

                        $fotos = \App\Models\FotoLocal::where('place_id', $record->id_lugar)
                            ->where('size_label', 'cover')
                            ->orderBy('id')
                            ->limit(12)
                            ->get();

                        return [
                            'images' => $fotos->map(fn($f) => [
                                'id' => $f->id,
                                'url' => route('media.local', $f->id),
                                'width' => $f->width,
                                'height' => $f->height,
                                'author' => $f->author_name,
                            ])->toArray(),
                            'field' => 'cover_id',
                            'columns' => 3,
                        ];
                    }),
            ])
            ->collapsible(),
    ])
    ->visible(fn (callable $get) => $get('seleccionar_imagenes_manualmente') && $get('descargar_fotos')),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            $svc = app(ImportarNegociosService::class);
                            $fotosService = app(FotosService::class);

                            // VALIDAR LISTING
                            $listing = \App\Models\NegocioPlataforma::find($data['id_listing']);
                            if (!$listing) {
                                throw new \Exception("El listing #{$data['id_listing']} no existe.");
                            }

                            // 1) LIMPIAR FOTOS DEL LOTE
                            if (!empty($data['limpiar_lote']) && $record->batch_token) {
                                DB::transaction(function () use ($record, $fotosService) {
                                    $todosLosPlaceIds = InstantaneaLugar::where('batch_token', $record->batch_token)
                                        ->pluck('id_lugar');

                                    if ($todosLosPlaceIds->isNotEmpty()) {
                                        $fotosService->eliminarFotosDeVariosLugares($todosLosPlaceIds->toArray());
                                    }

                                    $otros = InstantaneaLugar::where('batch_token', $record->batch_token)
                                        ->where('id_lugar', '<>', $record->id_lugar)
                                        ->pluck('id_lugar');

                                    if ($otros->isNotEmpty()) {
                                        InstantaneaLugar::whereIn('id_lugar', $otros)->delete();
                                    }
                                });
                            }

                            // 2) VINCULAR
                            $svc->vincularConPlataforma((int) $data['id_listing'], $record->id_lugar, 0.95, true);
                            $det = $svc->obtenerDetalles($record->id_lugar);

                            // 3) DESCARGAR FOTOS
                            if (!empty($data['descargar_fotos'])) {
                                if ((int) $data['max_fotos'] > 0 && !empty($det['photos'])) {
                                    $det['photos'] = array_slice($det['photos'], 0, (int) $data['max_fotos']);
                                }

                                $fotosService->importarFotosDeLugarSeleccionado(
                                    $det,
                                    [
                                        ['label' => 'thumb', 'w' => 400],
                                        ['label' => 'cover', 'w' => 1200],
                                        ['label' => 'full', 'w' => 2048],
                                    ]
                                );
                            }

                            // 4) MAPEAR Y SINCRONIZAR (con imágenes seleccionadas)
                            $opcionesMapeo = [];
                            if (!empty($data['thumbnail_id'])) {
                                $opcionesMapeo['thumbnail_id'] = $data['thumbnail_id'];
                            }
                            if (!empty($data['cover_id'])) {
                                $opcionesMapeo['cover_id'] = $data['cover_id'];
                            }

                            $map = app(MapeoPlacesAListingService::class)->mapear($det, $opcionesMapeo);
                            app(SincronizarListingService::class)->aplicar((int) $data['id_listing'], $map, 'places_sync');

                            // 5) APLICAR BLOQUEOS
                            if (!empty($data['aplicar_bloqueos_default'])) {
                                $camposDefault = [
                                    'categories',
                                    'description',
                                    'amenities',
                                    'photos',
                                    'video_url',
                                    'video_provider',
                                    'tags',
                                    'social',
                                    'seo_meta_tags',
                                    'meta_description',
                                    'listing_type',
                                    'listing_thumbnail',
                                    'listing_cover',
                                    'certifications',
                                    'price_range',
                                ];

                                foreach ($camposDefault as $campo) {
                                    \App\Models\BloqueoCampo::updateOrCreate(
                                        ['id_negocio_plataforma' => (int) $data['id_listing'], 'campo' => $campo],
                                        ['bloqueado' => true]
                                    );
                                }
                            }

                            if (!empty($data['bloqueos_personalizados'])) {
                                foreach ($data['bloqueos_personalizados'] as $campo) {
                                    \App\Models\BloqueoCampo::updateOrCreate(
                                        ['id_negocio_plataforma' => (int) $data['id_listing'], 'campo' => $campo],
                                        ['bloqueado' => true]
                                    );
                                }
                            }

                            Notification::make()
                                ->title('✅ Vinculación completada')
                                ->body("'{$listing->name}' vinculado exitosamente")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Log::error('Error al vincular', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);

                            Notification::make()
                                ->title('❌ Error en la vinculación')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            throw $e;
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
