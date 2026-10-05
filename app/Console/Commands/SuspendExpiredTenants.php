<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:suspend-expired-tenants')]
#[Description('Scan and suspend tenants whose trials or paid subscriptions have expired')]
class SuspendExpiredTenants extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting expired subscription scan...');
        $now = now();

        $expiredSubscriptions = Subscription::with('tenant')
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->where('ends_at', '<', $now)
                      ->orWhere('trial_ends_at', '<', $now);
            })
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            $this->info('No expired subscriptions found.');
            return;
        }

        $count = 0;

        DB::transaction(function () use (&$count, $expiredSubscriptions) {
            foreach ($expiredSubscriptions as $subscription) {
                $subscription->update(['status' => 'past_due']);

                if ($subscription->tenant) {
                    $subscription->tenant->update(['plan' => 'suspended']);
                }

                $count++;
                $this->line("Suspended Tenant ID: {$subscription->tenant_id}");
            }
        });

        $this->info("Successfully suspended {$count} tenants.");
    }
}
