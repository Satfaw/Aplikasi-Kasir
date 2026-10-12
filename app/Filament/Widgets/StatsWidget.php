<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected function getStats(): array
    {
        return [
            Stat::make('Total Products', Product::count())
                ->description('The Number of Product')
                ->descriptionIcon('heroicon-s-rectangle-stack', IconPosition::Before)
                ->chart([1, 5, 12, 8, 20])
                ->color('primary'),
            Stat::make('Total Customer', Customer::count())
                ->description('The Number of Customer')
                ->descriptionIcon('heroicon-s-user-group', IconPosition::Before)
                ->chart([1, 5, 12, 8, 20])
                ->color('primary'),
            Stat::make('Total Orders', Order::count())
                ->description('The Number of Orders Created')
                ->descriptionIcon('heroicon-s-shopping-bag', IconPosition::Before)
                ->chart([1, 5, 12, 8, 20])
                ->color('primary'),
            Stat::make('Total Revenue', 'IDR ' . number_format(Order::where('status', 'completed')->sum('total_payment'), 0))
                ->description('Total payment from completed orders')
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::Before)
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('primary'),
        ];
    }
}
