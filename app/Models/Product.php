<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'tenant_id', 'category_id', 'sku', 'image', 'description', 'stock', 'price', 'discount', 'final_price', 'is_visible'])]
class Product extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'price' => 'float',
            'discount' => 'float',
            'final_price' => 'float',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include visible records.
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where(column: 'is_visible', value: true);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }
}
