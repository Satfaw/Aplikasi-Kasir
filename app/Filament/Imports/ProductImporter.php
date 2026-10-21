<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('price')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('stock')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('image')
                ->rules(['max:255']),
            ImportColumn::make('brand')
                ->relationship(),
            ImportColumn::make('category')
                ->relationship(),
            ImportColumn::make('subcategory')
                ->relationship(),
            ImportColumn::make('is_active')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('in_stock')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('sku')
                ->label('SKU')
                ->rules(['max:255']),
            ImportColumn::make('barcode')
                ->rules(['max:255']),
            ImportColumn::make('description'),
            ImportColumn::make('base_price')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('uom')
                ->relationship(),
            ImportColumn::make('base_unit')
                ->rules(['max:255']),
            ImportColumn::make('purchase_unit')
                ->rules(['max:255']),
            ImportColumn::make('conversion_factor')
                ->rules(['max:255']),
            ImportColumn::make('gross_margin')
                ->numeric()
                ->rules(['integer']),
        ];
    }

    public function resolveRecord(): Product
    {
        return new Product();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
