<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Imports\ProductImporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Foto')
                    ->placeholder('-')
                    ->visibility('public')
                    ->alignCenter(),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),

                IconColumn::make('is_visible')
                    ->label('Visibilitas'),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(ProductImporter::class)
                    ->label('Unggah Produk')
                    ->icon(Heroicon::ArrowUpTray)
                    ->options([
                        'tenant_id' => Filament::getTenant()->id,
                    ]),
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
            ]);
    }
}
