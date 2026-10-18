<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LastOrders extends BaseWidget
{
    protected static ?int $sort = 5;
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                ->latest()
                ->take(5),
            )
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID'),
                TextColumn::make('customer.name')
                    ->sortable(),
                TextColumn::make('total_payment')
                    ->prefix('IDR ')
                    ->numeric(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
            ])->paginated(false);
    }
}
