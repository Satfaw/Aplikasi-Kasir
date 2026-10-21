<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\BaseUnit;
use App\Models\Uom;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->label('Purchase Information')
                            ->schema([
                                TextEntry::make('purchase_number')
                                    ->label('Purchase Number'),
                                TextEntry::make('user.name')
                                    ->label('Created By'),
                                TextEntry::make('purchase_date')
                                    ->label('Purchase Date')
                                    ->date()
                                    ->placeholder('-'),
                                TextEntry::make('received_date')
                                    ->label('Received Date')
                                    ->date()
                                    ->placeholder('-'),
                            ])->columns(4),
                        Section::make()
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),
                                TextEntry::make('status_payment')
                                    ->label('Status Payment')
                                    ->badge(),
                                TextEntry::make('payment_method')
                                    ->label('Payment Method')
                                    ->badge(),
                            ])->columns(3),
                    ])->columnSpan(2),
                Section::make('Supplier Information')
                    ->schema([
                        TextEntry::make('supplier.name')
                            ->label('Supplier Name'),
                        TextEntry::make('supplier.address')
                            ->label('Address'),
                        TextEntry::make('supplier.phone')
                            ->label('Phone'),
                    ])->columns(1),

                Section::make('Purchase Details')
                    ->schema([
                        RepeatableEntry::make('purchaseDetails')
                            ->hiddenLabel()
                            ->label('Purchase details')
                            ->schema([
                                ImageEntry::make('product.image')
                                    ->label('Image'),
                                TextEntry::make('product.name')
                                    ->label('Product Name'),
                                TextEntry::make('price')
                                    ->label('Price')
                                    ->money('IDR '),
                                TextEntry::make('Quantity')
                                    ->state(function ($record) {
                                        return ((int) $record->qty).' '.(Uom::find($record->purchase_unit)?->name);
                                    }),
                                TextEntry::make('Total Quantity')
                                    ->state(function ($record) {
                                        return ((int) $record->total_qty).' '.(BaseUnit::find($record->base_unit)?->name);
                                    }),
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('IDR '),
                            ])->columns(6),
                        Section::make()
                            ->label('Summary')
                            ->schema([
                                TextEntry::make('total_before_tax')
                                    ->label('Subtotal')
                                    ->money('IDR'),
                                TextEntry::make('tax_rate')
                                    ->label('Tax (%)'),
                                TextEntry::make('tax_amount')
                                    ->label('Tax Amount')
                                    ->money('IDR'),
                                TextEntry::make('discount')
                                    ->label('Discount (%)'),
                                TextEntry::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->money('IDR'),
                                TextEntry::make('total_payment')
                                    ->label('Total Payment')
                                    ->money('IDR'),
                            ])->columns(6),
                    ])->columnSpanFull(),
                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ])->columns(2),
            ])->columns(3);
    }
}
