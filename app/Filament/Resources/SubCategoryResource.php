<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Products\ProductsCluster;
use App\Filament\Resources\SubCategoryResource\Pages\CreateSubCategory;
use App\Filament\Resources\SubCategoryResource\Pages\EditSubCategory;
use App\Filament\Resources\SubCategoryResource\Pages\ListSubCategories;
use App\Filament\Resources\SubCategoryResource\Pages\ViewSubCategory;
use App\Models\SubCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubCategoryResource extends Resource
{
    protected static ?string $model = SubCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-square-2-stack';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'heroicon-s-square-2-stack';

    protected static ?string $cluster = ProductsCluster::class;

    protected static ?int $navigationSort = 4;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Name' => $record->name ?? 'N/A',
            'Category' => $record->category->name ?? 'N/A',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('image')
                    ->image(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                ImageColumn::make('image'),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListSubCategories::route('/'),
            'create' => CreateSubCategory::route('/create'),
            'view' => ViewSubCategory::route('/{record}'),
            'edit' => EditSubCategory::route('/{record}/edit'),
        ];
    }
}
