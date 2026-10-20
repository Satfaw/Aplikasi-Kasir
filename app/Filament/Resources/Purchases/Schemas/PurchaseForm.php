<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Product;
use App\Models\Purchase;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

            return 'PO-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
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
                        ->required(),

                    TextInput::make('created_by')
                        ->hiddenLabel()
                        ->prefix('Created By:')
                        ->default(fn () => Auth::user()?->name)
                        ->readOnly()
                        ->dehydrated(false),
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
                                        ->relationship('supplier', 'name')
                                        ->label('Supplier Name')
                                        ->searchable()
                                        ->preload(),

                                    DatePicker::make('purchase_date')
                                        ->label('Purchase Date'),

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
                                                ->relationship('product', 'name')
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $product = Product::find($state);
                                                    if ($product) {
                                                        $set('purchase_unit', $product->purchaseUnit?->code ?? '');
                                                        $set('base_unit', $product->BaseUnit?->code ?? '');
                                                        $set('conversion', $product->conversion_factor ?? 1);
                                                        $set('price', 0);
                                                        $set('subtotal', 0);
                                                        $set('total_qty', ($product->conversion_factor ?? 1) * 1);
                                                    }
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
                                                        ->default(1)
                                                        ->minValue(1)
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                            $qty = floatval($state ?? 1);
                                                            $conversion = floatval($get('conversion') ?? 1);
                                                            $set('total_qty', $qty * $conversion);
                                                            $price = floatval($get('price') ?? 0);
                                                            $set('subtotal', $qty * $price);
                                                            self::recalculateTotals($get, $set);
                                                        }),

                                                    TextInput::make('purchase_unit')
                                                        ->label('Purchase Unit')
                                                        ->placeholder('Unit')
                                                        ->disabled()
                                                        ->dehydrated(),
                                                ]),

                                            // Price — user inputs, default 0
                                            TextInput::make('price')
                                                ->label('Price')
                                                ->required()
                                                ->numeric()
                                                ->prefix('IDR')
                                                ->default(0)
                                                ->reactive()
                                                ->dehydrated()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $qty = floatval($get('qty') ?? 1);
                                                    $set('subtotal', $qty * floatval($state ?? 0));
                                                    self::recalculateTotals($get, $set);
                                                }),

                                            // Hidden conversion factor
                                            TextInput::make('conversion')
                                                ->label('Conversion')
                                                ->numeric()
                                                ->default(1)
                                                ->hidden()
                                                ->dehydrated(),

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

                                                    TextInput::make('base_unit')
                                                        ->label('Base Unit')
                                                        ->placeholder('Unit')
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
                                                ->label('Add to purchase details')
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
                                        'draft'     => 'Draft',
                                        'received'  => 'Received',
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
                                        ->default(11)
                                        ->suffix('%')
                                        ->required()
                                        ->numeric()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            $total = floatval($get('total_before_tax') ?? 0);
                                            $tax   = ($state / 100) * $total;
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
                                            $total    = floatval($get('total_before_tax') ?? 0);
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
                                            'paid'   => 'Paid',
                                        ])
                                        ->default('unpaid')
                                        ->required(),

                                    Select::make('payment_method')
                                        ->label('Payment method')
                                        ->options([
                                            'cash'   => 'Cash',
                                            'credit' => 'Credit',
                                            'debit'  => 'Debit',
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

        $taxRate        = floatval($get('../../tax_rate') ?? 11);
        $taxAmount      = $taxRate / 100 * $totalBeforeTax;
        $set('../../tax_amount', $taxAmount);

        $discountRate   = floatval($get('../../discount') ?? 0);
        $discountAmount = $discountRate / 100 * $totalBeforeTax;
        $set('../../discount_amount', $discountAmount);

        $set('../../total_payment', $totalBeforeTax + $taxAmount - $discountAmount);
    }
}