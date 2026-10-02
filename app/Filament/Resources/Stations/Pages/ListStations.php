<?php

namespace App\Filament\Resources\Stations\Pages;

use App\Filament\Resources\Stations\StationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStations extends ListRecords
{
    protected static string $resource = StationResource::class;

    protected static ?string $title = 'Panel';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::PlusCircle)
                ->label(__('filament-actions::create.single.label', ['label' => 'Panel'])),
        ];
    }
}
