<?php

namespace App\Filament\Resources\BaseUnits;

use App\Filament\Resources\BaseUnits\Pages\CreateBaseUnit;
use App\Filament\Resources\BaseUnits\Pages\EditBaseUnit;
use App\Filament\Resources\BaseUnits\Pages\ListBaseUnits;
use App\Filament\Resources\BaseUnits\Pages\ViewBaseUnit;
use App\Filament\Resources\BaseUnits\Schemas\BaseUnitForm;
use App\Filament\Resources\BaseUnits\Schemas\BaseUnitInfolist;
use App\Filament\Resources\BaseUnits\Tables\BaseUnitsTable;
use App\Models\BaseUnit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BaseUnitResource extends Resource
{
    protected static ?string $model = BaseUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return BaseUnitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BaseUnitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BaseUnitsTable::configure($table);
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
            'index' => ListBaseUnits::route('/'),
            'create' => CreateBaseUnit::route('/create'),
            'view' => ViewBaseUnit::route('/{record}'),
            'edit' => EditBaseUnit::route('/{record}/edit'),
        ];
    }
}
