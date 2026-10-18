<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\OrderDetailRelationManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \BackedEnum | null $activeNavigationIcon = 'heroicon-s-shopping-bag';
    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'customer.name'];
    }
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Order ID' => $record->id ?? 'N/A',
            'Customer Name' => $record->customer?->name ?? 'N/A',
        ];
    }


    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'new')->count();
    }

    #[Override]
    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::count() > 0 ? 'info' : 'primary';
    }

    #[Override]
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'The number of new orders';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('date')
                    ->default(now())
                    ->required()
                    ->disabled()
                    ->dehydrated()
                    ->hiddenLabel()
                    ->prefix('Date:')
                    ->columnSpanFull(),
                Group::make()
                    ->schema([
                        Section::make('')
                            ->description('Customer Information')
                            ->schema([
                                Select::make('customer_id')
                                    ->relationship('customer', 'name')
                                    ->label('Name')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $customer = Customer::find($state);
                                        $set('phone', $customer->phone ?? null);
                                        $set('address', $customer->address ?? null);
                                    })
                                    ->createOptionForm([
                                        TextInput::make('name'),
                                        TextInput::make('phone'),
                                        TextInput::make('address'),
                                    ]),
                                Placeholder::make('phone')
                                    ->content(fn(Get $get) => Customer::find($get('customer_id'))?->phone ?? '-'),
                                Placeholder::make('address')
                                    ->content(fn(Get $get) => Customer::find($get('customer_id'))?->address ?? '-'),
                            ])->columns(3),

                        Section::make()
                            ->description('Order Details')
                            ->schema([
                                Repeater::make('orderdetail')
                                    ->relationship()
                                    ->schema([
                                        Select::make('product_id')
                                            ->relationship('product', 'name', modifyQueryUsing: fn(Builder $query)
                                            => $query->where('is_active', true))
                                            ->reactive()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $product = Product::find($state);
                                                $price = $product->price ?? 0;
                                                $set('price', $price);
                                                $qty = $get('qty') ?? 1;
                                                $set('qty', $qty);
                                                $subtotal = $price * $qty;
                                                $set('subtotal', $subtotal);
                                                $items = $get('../../orderdetail') ?? [];
                                                $total = collect($items)->sum(fn($item) => $item['subtotal'] ?? 0);
                                                $set('../../total_price', $total);

                                                $tax_rate = $get('../../tax_rate');
                                                $tax_amount = $total * $tax_rate / 100;
                                                $set('../../tax_amount', $tax_amount);
                                                $set('../../total_payment', $total + $tax_amount);

                                                $discount = $get('../../discount');
                                                $discount_amount = $total * $discount / 100;
                                                $set('../../discount_amount', $discount_amount);
                                                $set('../../total_payment', $total - $discount_amount);
                                            })
                                            ->searchable()
                                            ->columnSpanFull(),
                                        TextInput::make('price')
                                            ->readOnly()
                                            ->numeric()
                                            ->step(1)
                                            ->formatStateUsing(fn($state, Get $get) => $state ?? Product::find($get('product_id'))?->price ?? 0)
                                            ->prefix('IDR'),
                                        TextInput::make('qty')
                                            ->numeric()
                                            ->default(1)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $price = $get('price') ?? 0;
                                                $set('subtotal', $price * $state);
                                                $items = $get('../../orderdetail') ?? [];
                                                $total = collect($items)->sum(fn($item) => $item['subtotal'] ?? 0);
                                                $set('../../total_price', $total);
                                                $tax_rate = $get('../../tax_rate');
                                                $tax_amount = $total * $tax_rate / 100;
                                                $set('../../tax_amount', $tax_amount);

                                                $discount = $get('../../discount');
                                                $discount_amount = $total * $discount / 100;
                                                $set('../../discount_amount', $discount_amount);
                                                $set('../../total_payment', $total - $discount_amount + $tax_amount);
                                            })
                                            ->minValue(1)
                                            ->maxValue(function (Get $get) {
                                                $productID = $get('product_id');
                                                $product = Product::find($productID);
                                                return $product?->stock ?? 0;
                                            }),
                                        TextInput::make('subtotal')
                                            ->numeric()
                                            ->step(1)
                                            ->readOnly()
                                            ->default(0)
                                            ->prefix('IDR'),
                                    ])->columns(3)
                                    ->hiddenLabel()
                                    ->addAction(
                                        fn(Action $action) => $action
                                            ->label('Add product')
                                            ->color('primary')
                                            ->icon('heroicon-o-plus')
                                    )
                            ]),

                    ])->columnSpan(2),

                Section::make()
                    ->description('Payment Information')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'new' => 'New',
                                'processing' => 'Processing',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                            ])->default('new')
                            ->columnSpanFull(),
                        TextInput::make('total_price')
                            ->required()
                            ->numeric()
                            ->readOnly()
                            ->columnSpanFull()
                            ->prefix('IDR')
                            ->default(0),
                        TextInput::make('tax_rate')
                            ->columnSpan(2)
                            ->default(11)
                            ->suffix('%')
                            ->readOnly(),
                        TextInput::make('tax_amount')
                            ->columnSpan(2)
                            ->default(0)
                            ->prefix('IDR '),
                        TextInput::make('discount')
                            ->columnSpan(2)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $discount = floatval($state) ?? 0;
                                $total_price = $get('total_price') ?? 0;
                                $discount_amount = $total_price * $discount / 100;
                                $set('discount_amount', $discount_amount);
                                $set('total_payment', $total_price - $discount_amount);
                            })
                            ->suffix('%')
                            ->default(0)
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('discount_amount')
                            ->columnSpan(2)
                            ->readOnly()
                            ->prefix('IDR')
                            ->default(0),
                        TextInput::make('total_payment')
                            ->columnSpanFull()
                            ->prefix('IDR')
                            ->default(0),
                        Select::make('payment_method')
                            ->columnSpan(2)
                            ->options([
                                'cash' => 'Cash',
                                'debit' => 'Debit',
                                'credit' => 'Credit',
                                'qris' => 'Qris',
                            ])->default('cash'),
                        Select::make('payment_status')
                            ->columnSpan(2)
                            ->options([
                                'unpaid' => 'Unpaid',
                                'paid' => 'Paid'
                            ])->default('unpaid')
                    ])->columnSpan(1)
                    ->columns(4),

            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID'),
                TextColumn::make('customer.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total_price')
                    ->prefix('IDR ')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tax_amount')
                    ->prefix('IDR')
                    ->numeric(),
                TextColumn::make('discount')
                    ->suffix('%'),
                TextColumn::make('discount_amount')
                    ->prefix('IDR')
                    ->numeric(),
                TextColumn::make('total_payment')
                    ->prefix('IDR ')
                    ->numeric(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'danger',
                    })->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'new' => 'info',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),

                TextColumn::make('date')
                    ->date()
                    ->sortable(),
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
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class)
                    ->label('Download Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderDetailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
