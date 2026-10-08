<?php

namespace App\Filament\Resources;

use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\OrderDetailRelationManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Filament\Tables\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;





class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('date')
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
                                    }),
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
                                            ->relationship('product', 'name')
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

                                                $discount = $get('../../discount');
                                                $discount_amount = $total * $discount / 100;
                                                $set('../../discount_amount', $discount_amount);
                                                $set('../../total_payment', $total - $discount_amount);
                                            }),
                                        TextInput::make('price')
                                            ->readOnly()
                                            ->numeric()
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
                                                $discount = $get('../../discount');
                                                $discount_amount = $total * $discount / 100;
                                                $set('../../discount_amount', $discount_amount);
                                                $set('../../total_payment', $total - $discount_amount);
                                            })
                                            ->minValue(1),
                                        TextInput::make('subtotal')
                                            ->numeric()
                                            ->readOnly()
                                            ->default(0)
                                            ->prefix('IDR'),
                                    ])->columns(4)
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
                Tables\Columns\TextColumn::make('customer.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total_price')
                    ->prefix('IDR ')
                    ->numeric()
                    ->sortable(),
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

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
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
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
