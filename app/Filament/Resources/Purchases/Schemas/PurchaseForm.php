<?php

namespace App\Filament\Resources\Purchases\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FusedGroup::make([
                    TextInput::make('purchase_number')
                        ->hiddenLabel()
                        ->disabled()
                        ->dehydrated()
                        ->columnSpanFull()
                        ->prefix('Purchase Number:')
                        ->required(),
                    TextInput::make('user_id')
                        ->hiddenLabel()
                        ->disabled()
                        ->dehydrated()
                        ->columnSpanFull()
                        ->prefix('Created By:')
                        ->required()
                        ->numeric(),
                ])->columnSpanFull(),
                Group::make([
                    Fieldset::make('Purchase Header')
                        ->schema([
                            TextInput::make('supplier_id')
                                ->label('Supplier Name')
                                ->required(),
                            DatePicker::make('purchase_date')
                                ->label('Purchase Date'),
                            DatePicker::make('received_date')
                                ->label('Received Date'),
                        ])->columns(3)
                ])->columnSpan(2),
                Fieldset::make('Payment Information')
                    ->schema([
                        Select::make('status')
                            ->options(['draft' => 'Draft', 'received' => 'Received', 'cancelled' => 'Cancelled'])
                            ->default('draft')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('subtotal')
                            ->default(0)
                            ->suffix('%')
                            ->required()
                            ->numeric()
                            ->columnSpanFull(),
                        FusedGroup::make([
                            TextInput::make('tax_rate')
                                ->default(0)
                                ->prefix('IDR ')
                                ->required()
                                ->numeric(),
                            TextInput::make('tax_amount')
                                ->default(0)
                                ->prefix('IDR ')
                                ->required()
                                ->numeric(),
                        ])->columns(2)
                            ->label('Tax')
                            ->columnSpanFull(),
                        FusedGroup::make([
                            TextInput::make('discount')
                                ->default(0)
                                ->suffix('%')
                                ->required()
                                ->numeric(),
                            TextInput::make('discount_amount')
                                ->default(0)
                                ->prefix('IDR ')
                                ->required()
                                ->numeric(),
                        ])->columns(2)
                            ->label('Discount')
                            ->columnSpanFull(),
                        TextInput::make('total_payment')
                            ->default(0)
                            ->prefix('IDR ')
                            ->required()
                            ->numeric()
                            ->columnSpanFull(),
                        Select::make('status_payment')
                            ->options(['paid' => 'Paid', 'unpaid' => 'Unpaid'])
                            ->required(),
                        Select::make('payment_method')
                            ->options(['cash' => 'Cash', 'credit' => 'Credit', 'debit' => 'Debit'])
                            ->default('cash')
                            ->required(),
                    ])->columnSpan(1),
            ])->columns(3);
    }
}
