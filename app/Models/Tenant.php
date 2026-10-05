<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function hasValidAccess(): bool
    {
        if ($this->plan === 'suspended') {
            return false;
        }

        $subscription = $this->currentSubscription;

        if (!$subscription) {
            return false;
        }

        if ($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()) {
            return true;
        }

        if ($subscription->status === 'active' && $subscription->ends_at && $subscription->ends_at->isFuture()) {
            return true;
        }

        return false;
    }

    public function hasFeature(string $featureKey): bool
    {
        if (!$this->hasValidAccess()) {
            return false;
        }

        $plan = $this->currentSubscription?->plan;

        if (!$plan || empty($plan->features)) {
            return false;
        }

        $value = $plan->features[$featureKey] ?? false;

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function getFeatureLimit(string $featureKey, $default = null)
    {
        if (!$this->hasValidAccess()) {
            return $default;
        }

        $plan = $this->currentSubscription?->plan;

        if (!$plan || empty($plan->features) || !isset($plan->features[$featureKey])) {
            return $default;
        }

        return $plan->features[$featureKey];
    }
}
