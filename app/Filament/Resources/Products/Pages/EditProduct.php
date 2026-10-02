<?php

namespace App\Filament\Resources\Products\Pages;

use App\Events\ProductDeleted;
use App\Events\ProductUpdated;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterSave(): void
    {
        ProductUpdated::dispatch($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon(Heroicon::Trash)
                ->after(function ($record) {
                    ProductDeleted::dispatch($record);
                }),
        ];
    }
}
