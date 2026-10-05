<?php

namespace App\Livewire;

use App\Models\Subscription;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class SubscriptionMetricWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return Auth::user()->isSystemAdmin();
    }

    protected function getStats(): array
    {
        $dateString = $this->pageFilters['date'] ?? null;

        $mrr = $this->calculateMonthlyRecuringRevenue();

        $activeSubscribers = Subscription::where('status', 'active')
            ->whereNotNull('starts_at')
            ->count();

        $activeTrials = Subscription::where('status', 'active')
            ->whereNull('starts_at')
            ->where('trial_ends_at', '>', now())
            ->count();

        return [
            Stat::make('Monthly Recurring Revenue', Number::currency($mrr, 'IDR', 'id'))
                ->description('Total expected monthly income')
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->color('success'),

            Stat::make('Active Subscribers', $activeSubscribers)
                ->description('Paying tenants')
                ->descriptionIcon(Heroicon::BuildingStorefront)
                ->color('info'),

            Stat::make('Active Trials', $activeTrials)
                ->description('Potential conversions')
                ->descriptionIcon(Heroicon::Sparkles)
                ->color('warning'),
        ];
    }

    private function calculateMonthlyRecuringRevenue(): float
    {
        $activeSubscriptions = Subscription::with('plan')
            ->where('status', 'active')
            ->whereNotNull('starts_at')
            ->get();

        return $activeSubscriptions->sum(function ($subscription) {
            $plan = $subscription->plan;

            if (! $plan) return 0;

            if ($plan->interval === 'year') {
                return $plan->price / 12;
            }

            return $plan->price;
        });
    }
}
