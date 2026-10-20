<?php

namespace App\Filament\Resources\BaseUnits\Pages;

use App\Filament\Resources\BaseUnits\BaseUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBaseUnit extends EditRecord
{
    protected static string $resource = BaseUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
