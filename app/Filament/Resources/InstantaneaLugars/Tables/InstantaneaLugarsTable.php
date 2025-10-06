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
use Filament\Forms\Components\Hidden;
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
                        // ========== PASO 1: CONFIGURACIÓN ==========
                        Step::make('Configuración')
                            ->schema([
                                // ✅ Campo hidden ÚNICO para controlar descarga
                                Hidden::make('fotos_descargando')
                                    ->default(false)
                                    ->reactive(),

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
                                    })
                                    ->disabled(fn(callable $get) => $get('fotos_descargando')),

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
                                            ->inline(false)
                                            ->reactive()
                                            ->disabled(fn(callable $get) => $get('fotos_descargando')),

                                        TextInput::make('max_fotos')
                                            ->label('Máximo de fotos a descargar')
                                            ->placeholder('Ej: 5, 10, 15...')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(50)
                                            ->helperText('Deja en blanco para descargar todas las fotos disponibles')
                                            ->visible(fn(callable $get) => $get('descargar_fotos'))
                                            ->disabled(fn(callable $get) => $get('fotos_descargando')),

                                        Toggle::make('limpiar_lote')
                                            ->label('Eliminar candidatos descartados')
                                            ->default(true)
                                            ->inline(false)
                                            ->disabled(fn(callable $get) => $get('fotos_descargando')),

                                        Toggle::make('seleccionar_imagenes_manualmente')
                                            ->label('Seleccionar thumbnail y cover manualmente')
                                            ->default(false)
                                            ->helperText(
                                                fn(callable $get) =>
                                                $get('fotos_descargando')
                                                ? '⏳ Descargando fotos, por favor espera...'
                                                : 'Si activas esto, se descargarán las fotos y podrás elegir cuáles usar'
                                            )
                                            ->inline(false)
                                            ->reactive()
                                            ->visible(fn(callable $get) => $get('descargar_fotos'))
                                            ->disabled(fn(callable $get) => $get('fotos_descargando'))
                                            ->afterStateUpdated(function ($state, callable $get, callable $set, $record) {
                                                // Solo procesar si se ACTIVA el toggle
                                                if ($state && $get('descargar_fotos') && $record) {
                                                    try {
                                                        $fotosService = app(FotosService::class);
                                                        $svc = app(ImportarNegociosService::class);

                                                        // Obtener detalles del lugar
                                                        $det = $svc->obtenerDetalles($record->id_lugar);

                                                        if (empty($det) || empty($det['photos'])) {
                                                            throw new \Exception('No se encontraron fotos para este lugar');
                                                        }

                                                        // Limitar fotos si se especificó
                                                        $maxFotos = (int) $get('max_fotos');
                                                        $cantidadOriginal = count($det['photos']);

                                                        if ($maxFotos > 0) {
                                                            $det['photos'] = array_slice($det['photos'], 0, $maxFotos);
                                                        }

                                                        $cantidadADescargar = count($det['photos']);

                                                        \Log::info('Iniciando descarga anticipada de fotos', [
                                                            'place_id' => $record->id_lugar,
                                                            'total_disponibles' => $cantidadOriginal,
                                                            'cantidad_a_descargar' => $cantidadADescargar,
                                                        ]);

                                                        // NOTA: Marcamos como descargando, pero la UI se actualizará
                                                        // solo DESPUÉS de que termine este callback completo
                                                        $set('fotos_descargando', true);

                                                        // Descargar fotos en alta calidad
                                                        $fotosService->importarFotosDeLugarSeleccionado(
                                                            $det,
                                                            [
                                                                ['label' => 'thumb', 'w' => 400],
                                                                ['label' => 'cover', 'w' => 1200],
                                                                ['label' => 'full', 'w' => 2048],
                                                            ]
                                                        );

                                                        \Log::info('Descarga anticipada completada', [
                                                            'place_id' => $record->id_lugar,
                                                            'cantidad_descargada' => $cantidadADescargar,
                                                        ]);

                                                        // DESBLOQUEAR UI
                                                        $set('fotos_descargando', false);

                                                        // Notificar éxito
                                                        Notification::make()
                                                            ->title('✅ Fotos descargadas')
                                                            ->body("Se descargaron {$cantidadADescargar} imágenes exitosamente. Ya puedes continuar al siguiente paso.")
                                                            ->success()
                                                            ->duration(5000)
                                                            ->send();

                                                    } catch (\Exception $e) {
                                                        // DESBLOQUEAR UI en caso de error
                                                        $set('fotos_descargando', false);

                                                        \Log::error('Error al descargar fotos anticipadamente', [
                                                            'error' => $e->getMessage(),
                                                            'place_id' => $record->id_lugar ?? null,
                                                            'trace' => $e->getTraceAsString()
                                                        ]);

                                                        Notification::make()
                                                            ->title('❌ Error al descargar fotos')
                                                            ->body('No se pudieron descargar las fotos: ' . $e->getMessage())
                                                            ->danger()
                                                            ->duration(8000)
                                                            ->send();

                                                        // Desactivar la selección manual si falló
                                                        $set('seleccionar_imagenes_manualmente', false);
                                                    }
                                                }

                                                // Si se DESACTIVA, limpiar las selecciones
                                                if (!$state) {
                                                    $set('thumbnail_id', null);
                                                    $set('cover_id', null);
                                                    $set('fotos_descargando', false);
                                                }
                                            }),
                                    ])
                                    ->collapsible(),

                                Section::make('🔒 Bloqueos Iniciales (Opcional)')
                                    ->schema([
                                        Toggle::make('aplicar_bloqueos_default')
                                            ->label('Aplicar bloqueos por defecto')
                                            ->default(false)
                                            ->inline(false)
                                            ->reactive()
                                            ->disabled(fn(callable $get) => $get('fotos_descargando')),

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
                                            ->visible(fn(callable $get) => !$get('aplicar_bloqueos_default'))
                                            ->disabled(fn(callable $get) => $get('fotos_descargando')),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ])
                            ->afterValidation(function (callable $get) {
                                // ✅ AQUÍ va la validación para evitar avanzar mientras descarga
                                if ($get('fotos_descargando')) {
                                    throw \Illuminate\Validation\ValidationException::withMessages([
                                        'fotos_descargando' => 'Por favor espera a que termine la descarga de fotos antes de continuar.',
                                    ]);
                                }
                            }),

                        // ========== PASO 2: SELECCIÓN DE IMÁGENES ==========
                        // ========== PASO 2: SELECCIÓN DE IMÁGENES CON RADIO BUTTONS ==========
                        Step::make('Selección de Imágenes')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('info_seleccion')
                                    ->content(new \Illuminate\Support\HtmlString(
                                        '<div class="bg-blue-50 dark:bg-blue-950 p-4 rounded-lg mb-4">
                    <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
                        📸 Selecciona las Imágenes para el Listing
                    </h3>
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        Selecciona una imagen de cada sección. Si no seleccionas ninguna, se usará la primera automáticamente.
                    </p>
                </div>'
                                    )),

                                Section::make('🖼️ Thumbnail (Miniatura)')
                                    ->description('Imagen pequeña que se muestra en listados y tarjetas (400px)')
                                    ->schema([
                                        \Filament\Forms\Components\Radio::make('thumbnail_id')
                                            ->label('Selecciona un Thumbnail')
                                            ->options(function ($record) {
                                                if (!$record)
                                                    return [];

                                                $fotos = FotoLocal::where('place_id', $record->id_lugar)
                                                    ->where('size_label', 'thumb')
                                                    ->orderBy('id')
                                                    ->limit(12)
                                                    ->get();

                                                if ($fotos->isEmpty()) {
                                                    return ['sin_fotos' => 'No hay thumbnails disponibles'];
                                                }

                                                return $fotos->mapWithKeys(function ($foto, $index) {
                                                    $number = $index + 1;
                                                    $author = $foto->author_name ? ' • © ' . \Illuminate\Support\Str::limit($foto->author_name, 20) : '';

                                                    return [
                                                        $foto->id => new \Illuminate\Support\HtmlString(
                                                            '<div class="flex items-center gap-3 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                        <img src="' . route('media.local', $foto->id) . '"
                                             class="w-24 h-24 object-cover rounded border border-gray-200 dark:border-gray-700 flex-shrink-0"
                                             loading="lazy" />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                Imagen #' . $number . '
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                ' . $foto->width . ' × ' . $foto->height . ' px' . $author . '
                                            </p>
                                        </div>
                                    </div>'
                                                        )
                                                    ];
                                                })->toArray();
                                            })
                                            ->default(null)
                                            ->dehydrated(true)
                                            ->helperText('Deja sin seleccionar para usar la primera imagen automáticamente'),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false),

                                Section::make('🎨 Cover (Portada)')
                                    ->description('Imagen grande que se muestra en la cabecera del listing (1200px)')
                                    ->schema([
                                        \Filament\Forms\Components\Radio::make('cover_id')
                                            ->label('Selecciona un Cover')
                                            ->options(function ($record) {
                                                if (!$record)
                                                    return [];

                                                $fotos = FotoLocal::where('place_id', $record->id_lugar)
                                                    ->where('size_label', 'cover')
                                                    ->orderBy('id')
                                                    ->limit(12)
                                                    ->get();

                                                if ($fotos->isEmpty()) {
                                                    return ['sin_fotos' => 'No hay covers disponibles'];
                                                }

                                                return $fotos->mapWithKeys(function ($foto, $index) {
                                                    $number = $index + 1;
                                                    $author = $foto->author_name ? ' • © ' . \Illuminate\Support\Str::limit($foto->author_name, 20) : '';

                                                    return [
                                                        $foto->id => new \Illuminate\Support\HtmlString(
                                                            '<div class="flex items-center gap-3 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                        <img src="' . route('media.local', $foto->id) . '"
                                             class="w-32 h-24 object-cover rounded border border-gray-200 dark:border-gray-700 flex-shrink-0"
                                             loading="lazy" />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                Imagen #' . $number . '
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                ' . $foto->width . ' × ' . $foto->height . ' px' . $author . '
                                            </p>
                                        </div>
                                    </div>'
                                                        )
                                                    ];
                                                })->toArray();
                                            })
                                            ->default(null)
                                            ->dehydrated(true)
                                            ->helperText('Deja sin seleccionar para usar la primera imagen automáticamente'),
                                    ])
                                    ->collapsible()
                                    ->collapsed(false),
                            ])
                            ->visible(fn(callable $get) => $get('seleccionar_imagenes_manualmente') && $get('descargar_fotos')),
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

                            // 1) LIMPIAR FOTOS DEL LOTE (EXCLUYENDO EL LUGAR ACTUAL)
                            if (!empty($data['limpiar_lote']) && $record->batch_token) {
                                DB::transaction(function () use ($record, $fotosService) {
                                    $otrosPlaceIds = InstantaneaLugar::where('batch_token', $record->batch_token)
                                        ->where('id_lugar', '<>', $record->id_lugar)
                                        ->pluck('id_lugar');

                                    if ($otrosPlaceIds->isNotEmpty()) {
                                        $fotosService->eliminarFotosDeVariosLugares($otrosPlaceIds->toArray());

                                        \Log::info('Fotos del lote eliminadas', [
                                            'batch_token' => $record->batch_token,
                                            'lugar_actual' => $record->id_lugar,
                                            'otros_eliminados' => $otrosPlaceIds->count()
                                        ]);
                                    }

                                    if ($otrosPlaceIds->isNotEmpty()) {
                                        InstantaneaLugar::where('batch_token', $record->batch_token)
                                            ->where('id_lugar', '<>', $record->id_lugar)
                                            ->delete();
                                    }
                                });
                            }

                            // 2) VINCULAR
                            $svc->vincularConPlataforma((int) $data['id_listing'], $record->id_lugar, 0.95, true);
                            $det = $svc->obtenerDetalles($record->id_lugar);

                            // 3) DESCARGAR FOTOS (SOLO SI NO SE DESCARGARON ANTES)
                            $seleccionManual = !empty($data['seleccionar_imagenes_manualmente']);
                            $descargarFotos = !empty($data['descargar_fotos']);

                            if ($descargarFotos && !empty($det['photos'])) {
                                $fotosExistentes = FotoLocal::where('place_id', $record->id_lugar)
                                    ->whereIn('size_label', ['thumb', 'cover', 'full'])
                                    ->exists();

                                if (!$fotosExistentes || !$seleccionManual) {
                                    if ((int) $data['max_fotos'] > 0) {
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

                                    \Log::info('Fotos descargadas en action()', [
                                        'place_id' => $record->id_lugar,
                                        'cantidad' => count($det['photos']),
                                    ]);
                                }
                            }

                            // 4) MAPEAR Y SINCRONIZAR (con imágenes seleccionadas)
                            $opcionesMapeo = [];

                            if (!empty($data['thumbnail_id'])) {
                                $opcionesMapeo['thumbnail_id'] = $data['thumbnail_id'];
                            }
                            if (!empty($data['cover_id'])) {
                                $opcionesMapeo['cover_id'] = $data['cover_id'];
                            }

                            $mapeoService = app(MapeoPlacesAListingService::class);
                            $map = $mapeoService->mapear($det, $opcionesMapeo);
                            app(SincronizarListingService::class)->aplicar((int) $data['id_listing'], $map, 'places_sync');

                            // 5) GUARDAR HORARIOS EN time_configuration
                            try {
                                $mapeoService->guardarHorarios((int) $data['id_listing'], $det);
                                \Log::info('Horarios guardados exitosamente', [
                                    'listing_id' => $data['id_listing']
                                ]);
                            } catch (\Exception $e) {
                                // No fallar la vinculación si falla el guardado de horarios
                                \Log::error('Error al guardar horarios', [
                                    'listing_id' => $data['id_listing'],
                                    'error' => $e->getMessage()
                                ]);
                            }

                            // 6) APLICAR BLOQUEOS
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

                            // Notificación mejorada
                            $mensajeImagenes = '';
                            if ($seleccionManual && (!empty($data['thumbnail_id']) || !empty($data['cover_id']))) {
                                $partes = [];
                                if (!empty($data['thumbnail_id']))
                                    $partes[] = 'thumbnail personalizado';
                                if (!empty($data['cover_id']))
                                    $partes[] = 'cover personalizado';
                                $mensajeImagenes = ' con ' . implode(' y ', $partes);
                            }

                            Notification::make()
                                ->title('✅ Vinculación completada')
                                ->body("'{$listing->name}' vinculado exitosamente{$mensajeImagenes}")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Log::error('Error al vincular', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'place_id' => $record->id_lugar ?? null,
                                'listing_id' => $data['id_listing'] ?? null,
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
