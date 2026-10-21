<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\BaseUnit;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Uom;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseForm
{
    public static function generatePurchaseNumber(): string
    {
        return DB::transaction(function () {
            $year = date('Y');

            $last = Purchase::whereYear('created_at', $year)
                ->lockForUpdate()
                ->orderByDesc('purchase_number')
                ->first();

            $next = $last
                ? intval(substr($last->purchase_number, -4)) + 1
                : 1;

            return 'PO-'.$year.'-'.str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([

                // ── Top bar ───────────────────────────────────────────────
                FusedGroup::make([
                    TextInput::make('purchase_number')
                        ->hiddenLabel()
                        ->prefix('Purchase Number:')
                        ->default(fn () => self::generatePurchaseNumber())
                        ->readOnly()
                        ->dehydrated()
                        ->required()
                        ->disabled(),

                    Select::make('user_id')
                        ->default(fn () => Auth::id())
                        ->options([Auth::id() => Auth::user()?->name])
                        ->hiddenLabel()
                        ->disabled()
                        ->dehydrated()
                        ->columnSpanFull()
                        ->prefix('Created By:')
                        ->required(),
                ])
                    ->columns(1)
                    ->extraAttributes(['class' => 'w-full']),

                // ── Main two-column grid ──────────────────────────────────
                Grid::make(5)
                    ->schema([

                        // ── Left (3/5) ────────────────────────────────────
                        Group::make([

                            Fieldset::make('Purchase Header')
                                ->columns(3)
                                ->schema([
                                    Select::make('supplier_id')
                                        ->required()
                                        ->relationship('supplier', 'name')
                                        ->label('Supplier Name')
                                        ->searchable()
                                        ->preload(),

                                    DatePicker::make('purchase_date')
                                        ->label('Purchase Date')
                                        ->default(today()->toDateString()),

                                    DatePicker::make('received_date')
                                        ->label('Received Date'),
                                ]),

                            Fieldset::make('Purchase Details')
                                ->schema([
                                    Repeater::make('purchaseDetails')
                                        ->relationship('purchaseDetails')
                                        ->hiddenLabel()
                                        ->columnSpanFull()
                                        ->columns(3)
                                        ->schema([

                                            // Product
                                            Select::make('product_id')
                                                ->label('Product')
                                                ->required()
                                                ->relationship('product', 'name')
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $product = Product::with(['BaseUnit', 'PurchaseUnit'])->find($state);

                                                    if (! $product) {
                                                        return;
                                                    }

                                                    if (! $get('purchase_unit')) {
                                                        $set('purchase_unit', $product->purchase_unit);
                                                    }

                                                    if (! $get('base_unit')) {
                                                        $set('base_unit', $product->base_unit);
                                                    }

                                                    $set('conversion', $product->conversion_factor);

                                                    if (! $get('qty')) {
                                                        $set('qty', 1);
                                                    }

                                                    $set('total_qty', $product->conversion_factor * $get('qty'));
                                                })->columnSpan(2),

                                            // Price — user inputs, default 0
                                            TextInput::make('price')
                                                ->label('Price')
                                                ->required()
                                                ->numeric()
                                                ->prefix('IDR')
                                                ->default(0)
                                                ->reactive()
                                                ->dehydrated()
                                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                                    $qty = $get('qty') ?? 1;
                                                    $set('subtotal', $qty * $state);

                                                    $items = $get('../../purchaseDetails') ?? [];
                                                    $total = collect($items)->sum(fn ($item) => $item['subtotal'] ?? 0);
                                                    $set('../../total_before_tax', $total);

                                                    $tax = $get('../../tax_rate');

                                                    if ($tax === null || $tax == 0) {
                                                        $tax = 11;
                                                        $set('../../tax_rate', $tax);
                                                    }

                                                    $taxAmount = ($tax / 100) * $total;
                                                    $set('../../tax_amount', $taxAmount);

                                                    $discount = $get('../../discount') ?? 0;
                                                    $discountAmount = ($discount / 100) * $total;
                                                    $set('../../discount_amount', $discountAmount);

                                                    $set('../../total_payment', $total + $taxAmount - $discountAmount);
                                                }),

                                            // Quantity (fused with purchase unit)
                                            FusedGroup::make()
                                                ->label('Quantity')
                                                ->columns(2)
                                                ->schema([
                                                    TextInput::make('qty')
                                                        ->label('Quantity')
                                                        ->required()
                                                        ->numeric()
                                                        ->default(0)
                                                        ->minValue(0)
                                                        ->rule('numeric', 'min:1')
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                            $qty = floatval($state ?? 1);
                                                            $conversion = floatval(Product::find($get('product_id'))?->conversion_factor ?? 1);
                                                            $set('conversion', $conversion);
                                                            $set('total_qty', $qty * $conversion);
                                                            $price = floatval($get('price') ?? 0);
                                                            $set('subtotal', $qty * $price);
                                                            self::recalculateTotals($get, $set);
                                                        }),

                                                    Select::make('purchase_unit')
                                                        ->options(Uom::pluck('name', 'id'))
                                                        ->placeholder('Unit')
                                                        ->label('Purchase Unit')
                                                        ->disabled()
                                                        ->dehydrated(),
                                                ]),

                                            // Hidden conversion factor
                                            Hidden::make('conversion')
                                                ->default(1),

                                            // Total Quantity (fused with base unit)
                                            FusedGroup::make()
                                                ->label('Total Quantity')
                                                ->columns(2)
                                                ->schema([
                                                    TextInput::make('total_qty')
                                                        ->label('Total Quantity')
                                                        ->numeric()
                                                        ->readOnly()
                                                        ->dehydrated()
                                                        ->default(0),

                                                    Select::make('base_unit')
                                                        ->options(BaseUnit::pluck('name', 'id'))
                                                        ->placeholder('Unit')
                                                        ->label('Base Unit')
                                                        ->disabled()
                                                        ->dehydrated(),
                                                ]),

                                            // Row subtotal
                                            FusedGroup::make()
                                                ->label('Subtotal')
                                                ->schema([
                                                    TextInput::make('subtotal')
                                                        ->label('Subtotal')
                                                        ->numeric()
                                                        ->prefix('IDR')
                                                        ->readOnly()
                                                        ->dehydrated()
                                                        ->default(0),
                                                ]),

                                        ])
                                        ->addAction(
                                            fn (Action $action) => $action
                                                ->label('Add purchase Order')
                                                ->color('primary')
                                                ->icon('heroicon-o-plus')
                                        )
                                        ->reactive(),
                                ]),

                        ])->columnSpan(3),

                        // ── Right (2/5) ───────────────────────────────────
                        Fieldset::make('Payment Information')
                            ->columnSpan(2)
                            ->columns(1)
                            ->schema([

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'received' => 'Received',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('draft')
                                    ->required(),

                                TextInput::make('total_before_tax')
                                    ->label('Total before tax')
                                    ->default(0)
                                    ->prefix('IDR')
                                    ->required()
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(),

                                FusedGroup::make([
                                    TextInput::make('tax_rate')
                                        ->reactive()
                                        ->default(0)
                                        ->suffix('%')
                                        ->required()
                                        ->numeric()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $total = floatval($get('total_before_tax') ?? 0);
                                            $tax = ($state / 100) * $total;
                                            $set('tax_amount', $tax);
                                            $discount = floatval($get('discount_amount') ?? 0);
                                            $set('total_payment', $total + $tax - $discount);
                                        }),

                                    TextInput::make('tax_amount')
                                        ->default(0)
                                        ->prefix('IDR')
                                        ->required()
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(),
                                ])
                                    ->columns(2)
                                    ->label('Tax'),

                                FusedGroup::make([
                                    TextInput::make('discount')
                                        ->default(0)
                                        ->suffix('%')
                                        ->numeric()
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $total = floatval($get('total_before_tax') ?? 0);
                                            $discount = ($state / 100) * $total;
                                            $set('discount_amount', $discount);
                                            $tax = floatval($get('tax_amount') ?? 0);
                                            $set('total_payment', $total + $tax - $discount);
                                        }),

                                    TextInput::make('discount_amount')
                                        ->default(0)
                                        ->prefix('IDR')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated()
                                        ->required(),
                                ])
                                    ->columns(2)
                                    ->label('Discount'),

                                TextInput::make('total_payment')
                                    ->label('Total payment')
                                    ->prefix('IDR')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->default(0)
                                    ->required(),

                                FusedGroup::make([
                                    Select::make('status_payment')
                                        ->label('Status payment')
                                        ->options([
                                            'unpaid' => 'Unpaid',
                                            'paid' => 'Paid',
                                        ])
                                        ->default('unpaid')
                                        ->required(),

                                    Select::make('payment_method')
                                        ->label('Payment method')
                                        ->options([
                                            'cash' => 'Cash',
                                            'credit' => 'Credit',
                                            'debit' => 'Debit',
                                        ])
                                        ->default('cash')
                                        ->required(),
                                ])
                                    ->columns(2)
                                    ->label('Payment'),

                            ]),

                    ]),
            ]);
    }

    public static function recalculateTotals(callable $get, callable $set): void
    {
        $items = $get('../../purchaseDetails') ?? [];

        $totalBeforeTax = collect($items)->sum(
            fn ($item) => floatval($item['subtotal'] ?? 0)
        );

        $set('../../total_before_tax', $totalBeforeTax);

        $taxRate = floatval($get('../../tax_rate') ?? 11);
        $taxAmount = $taxRate / 100 * $totalBeforeTax;
        $set('../../tax_amount', $taxAmount);

        $discountRate = floatval($get('../../discount') ?? 0);
        $discountAmount = $discountRate / 100 * $totalBeforeTax;
        $set('../../discount_amount', $discountAmount);

        $set('../../total_payment', $totalBeforeTax + $taxAmount - $discountAmount);
    }
}
