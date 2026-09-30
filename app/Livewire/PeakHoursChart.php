<?php

namespace App\Livewire;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class PeakHoursChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Jam Sibuk (Peak Hours)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $dateString = $this->pageFilters['date'] ?? null;

        if ($dateString) {
            $dates = explode(' - ', $dateString);
            $start = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->startOfDay();
            $end = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->endOfDay();
        } else {
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        }

        $hourlyData = Order::query()
            ->where('tenant_id', Filament::getTenant()->id)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->select([
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(id) as total_transactions')
            ])
            ->groupBy('hour')
            ->pluck('total_transactions', 'hour')
            ->toArray();

        $chartData = [];
        $labels = [];

        for ($i = 0; $i <= 23; $i++) {
            $chartData[] = $hourlyData[$i] ?? 0;
            $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Transaksi',
                    'data' => $chartData,
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
