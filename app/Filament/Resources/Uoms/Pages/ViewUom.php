<?php

namespace App\Filament\Resources\Uoms\Pages;

use App\Filament\Resources\Uoms\UomResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUom extends ViewRecord
{
    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
