<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class ProductResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public $attributes = [
        // ...
    ];

    /**
     * The resource's relationships.
     */
    public $relationships = [
        'tenant',
        'category',
        'variants',
    ];

    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'image' => $this->image,
            'description' => $this->description,
            'stock' => (int) $this->stock,
            'price' => (float) $this->price,
            'discount' => (int) $this->discount,
            'final_price' => (float) $this->final_price,
            'sort' => (int) $this->sort,
            'is_visible' => $this->is_visible,
        ];
    }
}
