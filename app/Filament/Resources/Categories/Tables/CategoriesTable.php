<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Events\CategoryReordered;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama'),

                IconColumn::make('is_visible')
                    ->label('Visibilitas'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort')
            ->afterReordering(function (array $order) {
                $tenant = Filament::getTenant()->slug;

                $newSortOrder = Category::whereHas('tenant', function ($query) use ($tenant) {
                    $query->where('slug', $tenant);
                })
                ->pluck('sort', 'name')
                ->toArray();

                CategoryReordered::dispatch($tenant, $newSortOrder);
            })
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering ? 'Simpan Pengurutan' : 'Pengurutan')
            )
            ->defaultSort('sort');
    }
}
