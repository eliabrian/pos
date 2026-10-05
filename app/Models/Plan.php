<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'price', 'interval', 'features', 'is_active', 'sort_order'])]
class Plan extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
