<?php

namespace App\Livewire;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class YearlyRevenueChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tren Penjualan Tahunan';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $dateString = $this->pageFilters['date'] ?? null;
        $year = Carbon::now()->year;

        if ($dateString) {
            $dates = explode(' - ', $dateString);
            if (count($dates) > 0) {
                $year = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->year;
            }
        }

        $previousYear = $year - 1;
        $tenantId = Filament::getTenant()->id;

        $monthlyData = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->select([
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_price) as total_revenue')
            ])
            ->groupBy('month')
            ->pluck('total_revenue', 'month')
            ->toArray();

        $previousMonthlyData = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', $previousYear)
            ->select([
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_price) as total_revenue')
            ])
            ->groupBy('month')
            ->pluck('total_revenue', 'month')
            ->toArray();

        $chartData = [];
        $previousChartData = [];

        for ($i = 1; $i <= 12; $i++) {
            $chartData[] = $monthlyData[$i] ?? 0;
            $previousChartData[] = $previousMonthlyData[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Penjualan (' . $year . ')',
                    'data' => $chartData,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => '#10b981',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Penjualan (' . $previousYear . ')',
                    'data' => $previousChartData,
                    'fill' => false,
                    'borderColor' => '#9ca3af',
                    'borderDash' => [5, 5],
                    'tension' => 0.3,
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
