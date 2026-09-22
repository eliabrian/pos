<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant as TenancyRegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Override;

class RegisterTenant extends TenancyRegisterTenant
{
    #[Override]
    public static function getLabel(): string
    {
        return 'Register Tenant';
    }

    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    #[Override]
    protected function handleRegistration(array $data): Model
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name'], ''),
            'plan' => 'trial',
        ]);

        $tenant->users()->attach(Auth::user());

        return $tenant;
    }
}
