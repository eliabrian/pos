<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantInfolist
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
                        TextEntry::make('name')
                            ->label('Tenant Name'),

                        TextEntry::make('slug')
                            ->label('Tenant Domain'),

                        TextEntry::make('phone')
                            ->placeholder('-'),

                        TextEntry::make('business_email')
                            ->label('Email')
                            ->placeholder('-'),
                    ]),

                Section::make('Tenant Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('plan')
                            ->badge()
                            ->getStateUsing(fn ($record): string => ucfirst($record->plan))
                            ->color(function ($record) {
                                return match($record->plan) {
                                    'trial' => 'primary',
                                    'active' => 'success',
                                    'suspended' => 'danger',
                                    default => 'primary',
                                };
                            })
                            ->columnSpanFull(),

                        TextEntry::make('created_at')
                            ->dateTime(format: 'd F Y')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->dateTime(format: 'd F Y')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
