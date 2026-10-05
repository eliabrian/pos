<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::PlusCircle),
        ];
    }
}
