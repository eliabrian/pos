<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Events\CategoryUpdated;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function afterSave(): void
    {
        CategoryUpdated::dispatch($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon(Heroicon::Trash),
        ];
    }
}
