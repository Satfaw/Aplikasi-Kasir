<?php

namespace App\Filament\Resources\Uoms\Pages;

use App\Filament\Resources\Uoms\UomResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUom extends EditRecord
{
    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
