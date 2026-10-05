<?php

namespace App\Livewire;

use App\Models\Plan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class PlanDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Plan Distribution Chart';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Auth::user()->isSystemAdmin();
    }

    protected function getData(): array
    {
        $plans = Plan::withCount(['subscriptions' => function ($query) {
            $query->where('status', 'active');
        }])->get();

        $labels = [];
        $data = [];
        $colors = [];

        $colorPalette = [
            '#3b82f6',
            '#8b5cf6',
            '#f59e0b',
            '#10b981',
            '#f43f5e',
        ];

        foreach ($plans as $index => $plan) {
            if ($plan->subscriptions_count > 0) {
                $labels[] = $plan->name;
                $data[] = $plan->subscriptions_count;
                $colors[] = $colorPalette[$index % count($colorPalette)];
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Subscribers',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'hoverOffset' => 4,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
