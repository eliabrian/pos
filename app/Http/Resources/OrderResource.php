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
        ];
    }
}
