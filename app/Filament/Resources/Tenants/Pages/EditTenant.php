<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Tenants\TenantResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    public function getRelationManagers(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon(Heroicon::Trash),
        ];
    }
}
