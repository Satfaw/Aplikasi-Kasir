<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue; 

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Order Chart';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = Trend::model(Order::class)
            ->between(
                start: now()->subMonth(6),
                end: now()->endOfYear(),
            )
            ->perMonth()        
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn(TrendValue $value) => date('M Y', strtotime($value->date)) ),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
