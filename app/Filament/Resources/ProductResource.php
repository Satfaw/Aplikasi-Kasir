<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $activeNavigationIcon = 'heroicon-s-rectangle-stack';
    protected static ?string $navigationGroup = 'Product Management';
    protected static ?int $navigationSort = 4;
        public static function getGloballySearchableAttributes(): array
    {
        return['name','sku','barcode','brand.name','category.name','subcategory.name'];
    }
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Name' =>$record->name ?? 'N/A',
            'SKU' =>$record->sku ?? 'N/A',
            'Barcode' =>$record->barcode ?? 'N/A',
            'Brand' =>$record->brand?->name ?? 'N/A',
            'Category' =>$record->category?->name ?? 'N/A',
            'Subcategory' =>$record->subcategory->name ?? 'N/A',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'The number of product';
    }
    public static function generateSku(Get $get, Set $set): void
    {
        $brand = Brand::find($get('brand_id'));
        $category = Category::find($get('category_id'));
        $subcategory = SubCategory::find($get('subcategory_id'));

        if (!$category || !$subcategory || !$brand) {
            return;
        }

        //ambil 3 huruf pertama

        $catCode = strtoupper(substr($category->name, 0, 3));
        $subCode = strtoupper(substr($subcategory->name, 0, 3));
        $brandCode = strtoupper(substr($brand->name, 0, 3));

        $lastSku = Product::where('category_id', $category->id)
            ->where('subcategory_id', $subcategory->id)
            ->where('brand_id', $brand->id)
            ->orderBy('id', 'desc')
            ->value('sku');

        $nextNumber = 1;
        if ($lastSku) {
            $parts = explode('-', $lastSku);
            $lastNumber = intval(end($parts));
            $nextNumber = $lastNumber + 1;
        }

        $sku = sprintf('%s-%s-%s-%03d', $catCode, $subCode, $brandCode, $nextNumber);
        $set('sku', $sku);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    Section::make([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('base_price')
                            ->numeric()
                            ->prefix('IDR '),

                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('IDR '),

                        Forms\Components\TextInput::make('stock')
                            ->required()
                            ->numeric(),

                        TextInput::make('sku'),

                        TextInput::make('barcode'),

                        Group::make([
                            Forms\Components\Toggle::make('is_active')
                                ->required(),

                            Forms\Components\Toggle::make('in_stock')
                                ->required(),
                        ]),

                        RichEditor::make('description')
                            ->columnSpanFull()

                    ])->columns(3)
                        ->description('Product Details'),
                ])->columnSpan(2),

                Section::make([
                    Select::make('brand_id')
                        ->relationship('brand', 'name', fn($query) => $query->where('is_active', true))
                        ->reactive()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            static::generateSku($get, $set);
                        }),
                    Select::make('category_id')
                        ->relationship('category', 'name', fn($query) => $query->where('is_active', true))
                        ->reactive()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            static::generateSku($get, $set);
                        }),
                    Select::make('subcategory_id')
                        ->label('Sub Category')
                        ->options(function (Get $get) {
                            $CategoryId = $get('category_id');
                            if (!$CategoryId) return [];
                            return SubCategory::where('category_id', $CategoryId)
                                ->pluck('name', 'id');
                        })->reactive()
                        ->disabled(fn(callable $get) => $get('category_id') === null)
                        ->dehydrated()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            static::generateSku($get, $set);
                        }),
                    Forms\Components\FileUpload::make('image')
                        ->image(),
                ])->columnSpan(1)
                    ->description('Association'),

            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('base_price')
                    ->money('IDR ')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('price')
                    ->money('IDR ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sku')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('barcode')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('brand.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subcategory.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('in_stock')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
