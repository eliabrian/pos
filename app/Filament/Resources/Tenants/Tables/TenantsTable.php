<?php

namespace App\Filament\Resources\Tenants\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plan')
                    ->getStateUsing(fn ($record): string => ucfirst($record->plan))
                    ->color(function ($record) {
                        return match($record->plan) {
                            'trial' => 'primary',
                            'active' => 'success',
                            'suspended' => 'danger',
                            default => 'primary',
                        };
                    })
                    ->badge(),

                TextColumn::make('business_email')
                    ->label('Email')
                    ->fontFamily(FontFamily::Mono)
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->placeholder('-'),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->icon(Heroicon::OutlinedPhone)
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Joined At')
                    ->dateTime(format: 'd F Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
