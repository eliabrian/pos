<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'receipt_number', 'total_price', 'status', 'payment_method'])]
class Order extends Model
{
    protected static function booted()
    {
        static::creating(function (Order $order) {
            $latestOrderToday = static::where('tenant_id', $order->tenant_id)
                ->whereDate('created_at', Carbon::now()->toDateString())
                ->latest('id')
                ->first();

            if (! $latestOrderToday) {
                $nextSequence = 1;
            } else {
                $lastSequenceString = substr($latestOrderToday->receipt_number, -5);
                $nextSequence = ((int) $lastSequenceString) + 1;
            }

            $order->receipt_number = sprintf(
                '%s-%s-%s',
                $order->tenant_id,
                Carbon::now()->format('dmY'),
                str_pad($nextSequence, 5, '0', STR_PAD_LEFT)
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(OrderProduct::class)
            ->withPivot(['unit_name', 'quantity', 'unit_price', 'sub_total', 'variant_selected', 'notes'])
            ->chaperone();
    }
}
