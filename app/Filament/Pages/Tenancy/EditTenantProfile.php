<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Resources\Tenants\Schemas\TenantForm;
use Filament\Facades\Filament;
use Filament\Pages\Tenancy\EditTenantProfile as TenancyEditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EditTenantProfile extends TenancyEditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Profil Tenant';
    }

    public function form(Schema $schema): Schema
    {
        return TenantForm::configure($schema);
    }
}
