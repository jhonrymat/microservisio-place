<?php

namespace App\Filament\Resources\LugarVinculados;

use App\Filament\Resources\LugarVinculados\Pages\CreateLugarVinculado;
use App\Filament\Resources\LugarVinculados\Pages\EditLugarVinculado;
use App\Filament\Resources\LugarVinculados\Pages\ListLugarVinculados;
use App\Filament\Resources\LugarVinculados\Schemas\LugarVinculadoForm;
use App\Filament\Resources\LugarVinculados\Tables\LugarVinculadosTable;
use App\Models\LugarVinculado;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LugarVinculadoResource extends Resource
{
    protected static ?string $model = LugarVinculado::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $recordTitleAttribute = 'id_lugar';

    public static function form(Schema $schema): Schema
    {
        return LugarVinculadoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LugarVinculadosTable::configure($table);
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
            'index' => ListLugarVinculados::route('/'),
            'create' => CreateLugarVinculado::route('/create'),
            'edit' => EditLugarVinculado::route('/{record}/edit'),
        ];
    }
}
