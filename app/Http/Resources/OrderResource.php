<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;


class OrderResource extends JsonApiResource
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
        'products',
    ];

    public function toAttributes(Request $request)
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'total_price' => $this->total_price,
            'payment_method' => $this->payment_method,
            'order_discount' => $this->order_discount,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'items' => $this->whenLoaded('products', function () {
                return $this->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $product->pivot->quantity,
                        'unit_price' => $product->pivot->unit_price,
                        'sub_total' => $product->pivot->sub_total,
                        'notes' => $product->pivot->notes,
                        'variant_selected' => $product->pivot->variant_selected,
                    ];
                });
            }),
        ];
    }
}
