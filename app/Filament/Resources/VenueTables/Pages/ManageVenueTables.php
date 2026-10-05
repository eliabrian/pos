<?php

namespace App\Filament\Resources\VenueTables\Pages;

use App\Filament\Resources\VenueTables\VenueTableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageVenueTables extends ManageRecords
{
    protected static string $resource = VenueTableResource::class;

    protected static ?string $title = 'Meja';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::PlusCircle)
                ->label('Buat Meja'),
        ];
    }
}
