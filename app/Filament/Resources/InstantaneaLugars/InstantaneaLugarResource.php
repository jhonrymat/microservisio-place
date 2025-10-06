<?php

namespace App\Filament\Resources\InstantaneaLugars;

use App\Filament\Resources\InstantaneaLugars\Pages\CreateInstantaneaLugar;
use App\Filament\Resources\InstantaneaLugars\Pages\EditInstantaneaLugar;
use App\Filament\Resources\InstantaneaLugars\Pages\ListInstantaneaLugars;
use App\Filament\Resources\InstantaneaLugars\Schemas\InstantaneaLugarForm;
use App\Filament\Resources\InstantaneaLugars\Tables\InstantaneaLugarsTable;
use App\Models\InstantaneaLugar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstantaneaLugarResource extends Resource
{
    protected static ?string $model = InstantaneaLugar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'id_lugar';

    public static function form(Schema $schema): Schema
    {
        return InstantaneaLugarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstantaneaLugarsTable::configure($table);
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
            'index' => ListInstantaneaLugars::route('/'),
            'create' => CreateInstantaneaLugar::route('/create'),
            'edit' => EditInstantaneaLugar::route('/{record}/edit'),
        ];
    }
}
