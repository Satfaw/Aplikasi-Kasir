<?php

namespace App\Filament\Widgets;

use App\Models\OrderDetail;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BestSelling extends BaseWidget
{
    protected static ?int $sort = 4;
    protected static ?string $heading = "Best Seller";
    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->product_id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                    fn() => OrderDetail::query()
                    ->select('product_id', DB::raw('SUM(qty) as total_sold'))
                    ->with('product')
                    ->groupBy('product_id')
                    ->orderByDesc('total_sold')
                    ->limit(5)
            )
            ->columns([
                ImageColumn::make('product_image')
                    ->label('image')
                    ->getStateUsing(fn($record)=>$record->product->image ?? 'N/A'),
                TextColumn::make('product_name')
                    ->label('Product Name')
                    ->getStateUsing(fn($record)=>$record->product->name ?? 'N/A'),
                TextColumn::make('total_sold')
                    ->label('Total Sold'),                 
            ])->paginated(false);
    }
}
