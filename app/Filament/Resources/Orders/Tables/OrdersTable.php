<?php

namespace App\Filament\Resources\Orders\Tables;

use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Zvizvi\FilamentColumnFilters\Filters\ColumnFilter;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Pembayaran')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'refund' => 'danger',
                        'void' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                DateRangeFilter::make('created_at')
                    ->defaultToday(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
