<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Produk';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::PlusCircle)
                ->label('Buat Produk'),
        ];
    }
}
