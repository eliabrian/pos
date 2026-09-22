<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

#[Fillable(['product_id', 'name', 'is_required', 'allow_multiple'])]
class Variant extends Model
{
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'allow_multiple' => 'boolean',
            'max_selectable' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variantItems(): HasMany
    {
        return $this->hasMany(VariantItem::class);
    }
}
