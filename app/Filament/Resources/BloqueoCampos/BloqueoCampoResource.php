<?php

namespace App\Filament\Resources\BloqueoCampos;

use BackedEnum;
use Filament\Tables\Table;
use App\Models\BloqueoCampo;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\BloqueoCampos\Pages\EditBloqueoCampo;
use App\Filament\Resources\BloqueoCampos\Pages\GestionarBloqueos;
use App\Filament\Resources\BloqueoCampos\Pages\ListBloqueoCampos;
use App\Filament\Resources\BloqueoCampos\Pages\CreateBloqueoCampo;
use App\Filament\Resources\BloqueoCampos\Schemas\BloqueoCampoForm;
use App\Filament\Resources\BloqueoCampos\Tables\BloqueoCamposTable;

class BloqueoCampoResource extends Resource
{
    protected static ?string $model = BloqueoCampo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $recordTitleAttribute = 'campo';

     protected static ?string $navigationLabel = 'Bloqueos de Campos';

    protected static ?string $modelLabel = 'Bloqueo';

    protected static ?string $pluralModelLabel = 'Bloqueos de Campos';


    public static function form(Schema $schema): Schema
    {
        return BloqueoCampoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BloqueoCamposTable::configure($table);
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
            'index' => ListBloqueoCampos::route('/'),
            'gestionar' => GestionarBloqueos::route('/gestionar'),
        ];
    }
}
