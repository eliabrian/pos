<?php

namespace App\Livewire;

use App\Models\Subscription;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class SubscriptionMetricChart extends ChartWidget
{
    protected ?string $heading = 'Subscription Metric Chart';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Auth::user()->isSystemAdmin();
    }

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');

            $mrrAdded = Subscription::with('plan')
                ->where('status', 'active')
                ->whereNotNull('starts_at')
                ->whereYear('starts_at', $month->year)
                ->whereMonth('starts_at', $month->month)
                ->get()
                ->sum(function ($subscription) {
                    $plan = $subscription->plan;
                    if (!$plan) return 0;

                    return $plan->interval === 'year' ? ($plan->price / 12) : $plan->price;
                });

            $data[] = $mrrAdded;
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Monthly Recurring Revenue',
                    'data' => $data,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => '#10b981',
                    'tension' => 0.4,
                ]
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
