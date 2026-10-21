<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Products\ProductsCluster;
use App\Filament\Exports\OrderExporter;
use App\Filament\Exports\ProductExporter;
use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\Pages\ViewProduct;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\Uom;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'heroicon-s-rectangle-stack';

    protected static ?string $cluster = ProductsCluster::class;

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode', 'brand.name', 'category.name', 'subcategory.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Name' => $record->name ?? 'N/A',
            'SKU' => $record->sku ?? 'N/A',
            'Barcode' => $record->barcode ?? 'N/A',
            'Brand' => $record->brand?->name ?? 'N/A',
            'Category' => $record->category?->name ?? 'N/A',
            'Subcategory' => $record->subcategory->name ?? 'N/A',
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

        if (! $category || ! $subcategory || ! $brand) {
            return;
        }

        // ambil 3 huruf pertama

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('base_price')
                            ->numeric()
                            ->prefix('IDR ')
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $set('gross_margin', ($get('price') ?? 0) - ($get('base_price') ?? 0));
                            }),

                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('IDR ')
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $set('gross_margin', ($get('price') ?? 0) - ($get('base_price') ?? 0));
                            }),

                        TextInput::make('gross_margin')
                            ->label('Gross Margin')
                            ->required()
                            ->numeric()
                            ->prefix('IDR ')
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $set('gross_margin', ($get('price') ?? 0) - ($get('base_price') ?? 0));
                            })
                            ->afterStateHydrated(function (callable $get, callable $set) {
                                $set('gross_margin', ($get('price') ?? 0) - ($get('base_price') ?? 0));
                            }),
                        Select::make('uom_id')
                            ->label('UoM Code')
                            ->relationship('uom', 'code')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $uom = Uom::with('base_unit')->find($state);
                                if (! $uom) {
                                    return;
                                }

                                if (! $get('base_unit')) {
                                    $set('base_unit', $uom->base_unit?->id);
                                }

                                if (! $get('purchase_unit')) {
                                    $set('purchase_unit', $uom->id);
                                }
                            })
                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                $uom = Uom::with('base_unit')->find($state);
                                if (! $uom) {
                                    return;
                                }

                                if (! $get('base_unit')) {
                                    $set('base_unit', $uom->base_unit?->id);
                                }

                                if (! $get('purchase_unit')) {
                                    $set('purchase_unit', $uom->id);
                                }
                            }),
                        Select::make('base_unit')
                            ->label('Base Unit')
                            ->options(BaseUnit::pluck('name', 'id'))
                            ->reactive(),
                        Select::make('purchase_unit')
                            ->label('Purchase Unit')
                            ->options(Uom::pluck('name', 'id'))
                            ->reactive(),
                        TextInput::make('conversion_factor')
                            ->label('Conversion'),

                        TextInput::make('stock')
                            ->required()
                            ->numeric(),

                        TextInput::make('sku'),

                        TextInput::make('barcode'),

                        Group::make([
                            Toggle::make('is_active')
                                ->required(),

                            Toggle::make('in_stock')
                                ->required(),
                        ]),

                        RichEditor::make('description')
                            ->columnSpanFull(),

                    ])->columns(3)
                        ->description('Product Details'),
                ])->columnSpan(2),

                Section::make([
                    Select::make('brand_id')
                        ->relationship('brand', 'name', fn($query) => $query->where('is_active', true))
                        ->reactive()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            static::generateSku($get, $set);
                        })
                        ->createOptionForm([
                            TextInput::make('name'),
                            FileUpload::make('image'),
                            Toggle::make('is_active'),
                        ]),
                    Select::make('category_id')
                        ->relationship('category', 'name', fn($query) => $query->where('is_active', true))
                        ->reactive()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            static::generateSku($get, $set);
                        })
                        ->createOptionForm([
                            TextInput::make('name'),
                            FileUpload::make('image'),
                            Toggle::make('is_active'),
                        ]),
                    Select::make('subcategory_id')
                        ->label('Sub Category')
                        ->options(function (Get $get) {
                            $CategoryId = $get('category_id');
                            if (! $CategoryId) {
                                return [];
                            }

                            return SubCategory::where('category_id', $CategoryId)
                                ->pluck('name', 'id');
                        })->reactive()
                        ->disabled(fn(callable $get) => $get('category_id') === null)
                        ->dehydrated()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            static::generateSku($get, $set);
                        })->createOptionForm([
                            Select::make('category_id')
                                ->options(Category::pluck('name', 'id')),
                            TextInput::make('name'),
                            FileUpload::make('image'),
                            Toggle::make('is_active'),
                        ])->createOptionUsing(function (array $data): int {
                            return SubCategory::create($data)->getKey('id');
                        }),
                    FileUpload::make('image')
                        ->image(),
                ])->columnSpan(1)
                    ->description('Association'),

            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('base_price')
                    ->money('IDR ')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price')
                    ->money('IDR ')
                    ->sortable(),
                TextColumn::make('stock')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sku')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('barcode')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brand.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subcategory.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('in_stock')
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
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category')
                    ->searchable()
                    ->preload(),
            ])

            ->headerActions([
                ExportAction::make()
                    ->exporter(ProductExporter::class)
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success'),
                ImportAction::make()
                    ->importer(ProductImporter::class)
                    ->label('Import')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info'),
            ])

            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
