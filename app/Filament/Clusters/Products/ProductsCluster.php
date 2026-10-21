<?php

namespace App\Filament\Clusters\Products;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ProductsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Squares2x2;

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $clusterBreadcrumb = 'Products';

    protected static ?int $navigationSort = 1;
}
