<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Tenant Informations')
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Tenant Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('Tenant Domain')
                            ->required()
                            ->maxLength(255)
                            ->prefix('https://')
                            ->suffix('pos.test')
                            ->unique(ignoreRecord: true),

                        TextInput::make('phone')
                            ->label('Phone')
                            ->placeholder('+62-812-3456-7890'),

                        TextInput::make('business_email')
                            ->label('Email')
                            ->maxLength('255')
                            ->email()
                            ->placeholder('example@email.com'),

                        TextInput::make('address')
                            ->label('Address')
                            ->maxLength('255')
                            ->columnSpanFull(),
                    ]),

                Section::make('Tenant Status')
                    ->schema([
                        Select::make('plan')
                            ->options([
                                'trial' => 'Trial',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->native(false)
                            ->default('trial')
                            ->required(),
                    ]),
            ]);
    }
}
