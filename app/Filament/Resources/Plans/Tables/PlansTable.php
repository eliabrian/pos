<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('price')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('interval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'month' => 'info',
                        'year' => 'success',
                    })
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'month' => 'Monthly',
                        'year' => 'Yearly'
                    }),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('subscriptions_count')
                    ->counts('subscriptions')
                    ->label('Active Subs')

            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
