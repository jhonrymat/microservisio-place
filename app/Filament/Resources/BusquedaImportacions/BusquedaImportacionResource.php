<?php

namespace App\Filament\Resources\BusquedaImportacions;

use App\Filament\Resources\BusquedaImportacions\Pages\CreateBusquedaImportacion;
use App\Filament\Resources\BusquedaImportacions\Pages\EditBusquedaImportacion;
use App\Filament\Resources\BusquedaImportacions\Pages\ListBusquedaImportacions;
use App\Filament\Resources\BusquedaImportacions\Schemas\BusquedaImportacionForm;
use App\Filament\Resources\BusquedaImportacions\Tables\BusquedaImportacionsTable;
use App\Models\BusquedaImportacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BusquedaImportacionResource extends Resource
{
    protected static ?string $model = BusquedaImportacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

        protected static ?string $navigationLabel = 'Búsquedas de Importación';

    protected static ?string $modelLabel = 'Búsqueda de Importación';

    protected static ?string $pluralModelLabel = 'Búsquedas de Importación';

    protected static ?string $recordTitleAttribute = 'search_key';


    public static function form(Schema $schema): Schema
    {
        return BusquedaImportacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusquedaImportacionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusquedaImportacions::route('/'),
            'create' => CreateBusquedaImportacion::route('/create'),
            'edit' => EditBusquedaImportacion::route('/{record}/edit'),
        ];
    }
}
