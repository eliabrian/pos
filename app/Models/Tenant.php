<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'plan', 'address', 'phone', 'business_email', 'qris_client_id', 'payment_client_id', 'payment_api_key', 'payment_secret_key', 'rsa_private_key', 'rsa_public_key'])]
class Tenant extends Model
{
    protected function casts()
    {
        return [
            'qris_client_id' => 'encrypted',
            'payment_client_id' => 'encrypted',
            'payment_api_key' => 'encrypted',
            'payment_secret_key' => 'encrypted',
            'rsa_private_key' => 'encrypted',
            'rsa_public_key' => 'encrypted',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }
}
