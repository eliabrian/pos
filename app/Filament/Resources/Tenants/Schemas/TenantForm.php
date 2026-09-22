<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Informasi Tenant')
                    ->columns(2)
                    ->columnSpan(fn () => Auth::user()->isSystemAdmin() ? 2 : 3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Tenant')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('Domain Tenant')
                            ->required()
                            ->maxLength(255)
                            ->prefix('https://')
                            ->suffix('pos.test')
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (): bool => ! Auth::user()->isSystemAdmin()),

                        TextInput::make('phone')
                            ->label('Telepon')
                            ->placeholder('+62-812-3456-7890'),

                        TextInput::make('business_email')
                            ->label('Email')
                            ->maxLength('255')
                            ->email()
                            ->placeholder('example@email.com'),

                        Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->maxLength('255')
                            ->columnSpanFull(),
                    ]),

                Section::make('Tenant Status')
                    ->visible(fn () => Auth::user()->isSystemAdmin())
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
