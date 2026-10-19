<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->autofocus()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('address')
                    ->required(),
                TextInput::make('cp_name')
                    ->label('Sales Name')
                    ->required(),
                TextInput::make('cp_phone')
                    ->label('Phone Number')
                    ->tel()
                    ->required(),
                TextInput::make('cp_email')
                    ->label('Email Address')
                    ->email()
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
