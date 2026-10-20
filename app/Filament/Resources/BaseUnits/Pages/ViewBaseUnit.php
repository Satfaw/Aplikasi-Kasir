<?php

namespace App\Filament\Resources\BaseUnits\Pages;

use App\Filament\Resources\BaseUnits\BaseUnitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBaseUnit extends ViewRecord
{
    protected static string $resource = BaseUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
