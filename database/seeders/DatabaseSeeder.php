<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Elia Brian Baskoro',
            'email' => 'eliabrianb@gmail.com',
            'is_system_admin' => true,
        ]);

        $tenant = Tenant::create([
            'name' => config('app.name'),
            'slug' => Str::slug(config('app.name'), ''),
        ]);

        $tenant->users()->attach($user);
    }
}
