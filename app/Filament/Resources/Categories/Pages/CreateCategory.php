<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Events\CategoryUpdated;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected static ?string $title = 'Buat Kategori';

    protected function afterCreate(): void
    {
        CategoryUpdated::dispatch($this->record);
    }
}
