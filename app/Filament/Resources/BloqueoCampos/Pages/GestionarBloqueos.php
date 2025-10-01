<?php
// app/Filament/Resources/BloqueoCampos/Pages/GestionarBloqueos.php
namespace App\Filament\Resources\BloqueoCampos\Pages;

use App\Models\BloqueoCampo;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\NegocioPlataforma;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Filament\Resources\BloqueoCampos\BloqueoCampoResource;

class GestionarBloqueos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = BloqueoCampoResource::class;

    // 👇 En Filament 4, NO es estática
    protected static ?string $title = 'Gestionar Bloqueos por Listing';

    // 👇 Método para retornar la vista (no propiedad estática)
    public function getView(): string
    {
        return 'filament.pages.gestionar-bloqueos';
    }

    public ?array $data = [];
    public ?int $listingSeleccionado = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                    Section::make('Seleccionar Listing')
                        ->description('Busca el listing para gestionar sus bloqueos de campos')
                        ->schema([
                                Select::make('listing_id')
                                    ->label('Buscar Listing')
                                    ->searchable()
                                    ->required()
                                    ->placeholder('Escribe para buscar...')
                                    ->getSearchResultsUsing(function (string $search) {
                                        return NegocioPlataforma::query()
                                            ->where(function ($query) use ($search) {
                                                $query->where('name', 'like', "%{$search}%")
                                                    ->orWhere('id', '=', $search);
                                            })
                                            ->limit(20)
                                            ->get()
                                            ->mapWithKeys(function ($listing) {
                                                return [$listing->id => "#{$listing->id} - {$listing->name}"];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $listing = NegocioPlataforma::find($value);
                                        return $listing ? "#{$listing->id} - {$listing->name}" : "Listing #{$value}";
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $this->listingSeleccionado = $state;
                                            $this->cargarBloqueosActuales($state, $set);
                                        }
                                    }),
                            ]),

                    Section::make('Bloqueos de Campos')
                        ->description('Selecciona los campos que NO deben ser actualizados por sincronizaciones automáticas')
                        ->schema([
                                Grid::make(2)
                                    ->schema([
                                            // Información Básica
                                            Section::make('📋 Información Básica')
                                                ->schema([
                                                        Toggle::make('bloq_name')
                                                            ->label('Nombre del negocio')
                                                            ->inline(false),
                                                        Toggle::make('bloq_address')
                                                            ->label('Dirección')
                                                            ->inline(false),
                                                        Toggle::make('bloq_phone')
                                                            ->label('Teléfono')
                                                            ->inline(false),
                                                        Toggle::make('bloq_email')
                                                            ->label('Email')
                                                            ->inline(false),
                                                        Toggle::make('bloq_website')
                                                            ->label('Sitio Web')
                                                            ->inline(false),
                                                    ]),

                                            // Contenido
                                            Section::make('📝 Contenido')
                                                ->schema([
                                                        Toggle::make('bloq_description')
                                                            ->label('Descripción')
                                                            ->inline(false),
                                                        Toggle::make('bloq_categories')
                                                            ->label('Categorías')
                                                            ->inline(false),
                                                        Toggle::make('bloq_amenities')
                                                            ->label('Amenidades')
                                                            ->inline(false),
                                                        Toggle::make('bloq_tags')
                                                            ->label('Etiquetas')
                                                            ->inline(false),
                                                        Toggle::make('bloq_certifications')
                                                            ->label('Certificaciones')
                                                            ->inline(false),
                                                    ]),

                                            // Media
                                            Section::make('📸 Media')
                                                ->schema([
                                                        Toggle::make('bloq_photos')
                                                            ->label('Galería de fotos')
                                                            ->inline(false),
                                                        Toggle::make('bloq_listing_thumbnail')
                                                            ->label('Miniatura')
                                                            ->inline(false),
                                                        Toggle::make('bloq_listing_cover')
                                                            ->label('Portada')
                                                            ->inline(false),
                                                        Toggle::make('bloq_video_url')
                                                            ->label('Video URL')
                                                            ->inline(false),
                                                        Toggle::make('bloq_video_provider')
                                                            ->label('Proveedor de video')
                                                            ->inline(false),
                                                    ]),

                                            // Otros
                                            Section::make('⚙️ Otros')
                                                ->schema([
                                                        Toggle::make('bloq_social')
                                                            ->label('Redes sociales')
                                                            ->inline(false),
                                                        Toggle::make('bloq_seo_meta_tags')
                                                            ->label('Meta tags SEO')
                                                            ->inline(false),
                                                        Toggle::make('bloq_meta_description')
                                                            ->label('Meta descripción')
                                                            ->inline(false),
                                                        Toggle::make('bloq_price_range')
                                                            ->label('Rango de precios')
                                                            ->inline(false),
                                                    ]),
                                        ]),
                            ])
                        ->visible(fn() => $this->listingSeleccionado !== null),
                ])
            ->statePath('data');
    }

    protected function cargarBloqueosActuales(int $listingId, callable $set): void
    {
        $bloqueos = BloqueoCampo::where('id_negocio_plataforma', $listingId)
            ->where('bloqueado', true)
            ->pluck('campo')
            ->toArray();

        foreach ($bloqueos as $campo) {
            $set("bloq_{$campo}", true);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aplicar_bloqueos_default')
                ->label('Aplicar Bloqueos por Defecto')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Aplicar Bloqueos por Defecto')
                ->modalDescription('Se bloquearán los campos sensibles según la configuración predeterminada (categorías, descripción, amenidades, fotos, videos, tags, social, SEO, etc.)')
                ->visible(fn() => $this->listingSeleccionado !== null)
                ->action(function () {
                    if (!$this->listingSeleccionado) {
                        return;
                    }

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
                        'opened_minutes',
                        'closed_minutes',
                        'certifications',
                        'price_range',
                    ];

                    foreach ($camposDefault as $campo) {
                        BloqueoCampo::updateOrCreate(
                            [
                                'id_negocio_plataforma' => $this->listingSeleccionado,
                                'campo' => $campo
                            ],
                            ['bloqueado' => true]
                        );
                    }

                    Notification::make()
                        ->title('✅ Bloqueos aplicados')
                        ->body('Se aplicaron los bloqueos por defecto al listing')
                        ->success()
                        ->send();

                    // Recargar
                    $this->cargarBloqueosActuales($this->listingSeleccionado, fn($k, $v) => $this->data[$k] = $v);
                }),

            Action::make('guardar')
                ->label('Guardar Bloqueos')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn() => $this->listingSeleccionado !== null)
                ->action('guardarBloqueos'),
        ];
    }

    public function guardarBloqueos(): void
    {
        if (!$this->listingSeleccionado) {
            Notification::make()
                ->title('Error')
                ->body('Debes seleccionar un listing primero')
                ->danger()
                ->send();
            return;
        }

        $data = $this->form->getState();

        // Campos disponibles
        $campos = [
            'name',
            'address',
            'phone',
            'email',
            'website',
            'description',
            'categories',
            'amenities',
            'tags',
            'certifications',
            'photos',
            'listing_thumbnail',
            'listing_cover',
            'video_url',
            'video_provider',
            'social',
            'seo_meta_tags',
            'meta_description',
            'price_range',
        ];

        foreach ($campos as $campo) {
            $bloqueado = $data["bloq_{$campo}"] ?? false;

            BloqueoCampo::updateOrCreate(
                [
                    'id_negocio_plataforma' => $this->listingSeleccionado,
                    'campo' => $campo
                ],
                ['bloqueado' => $bloqueado]
            );
        }

        Notification::make()
            ->title('✅ Bloqueos guardados')
            ->body('Los bloqueos se actualizaron correctamente')
            ->success()
            ->send();
    }
}
