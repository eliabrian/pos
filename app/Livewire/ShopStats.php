<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Override;

class ShopStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return !Auth::user()->isSystemAdmin();
    }

    protected function getListeners(): array
    {
        $tenant = Filament::getTenant()->slug;
        return [
            "echo-private:dashboard.{$tenant},.OrderCompleted" => '$refresh',
        ];
    }

    protected function getColumns(): int|array|null
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 4,
        ];
    }

    protected function getStats(): array
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

        $daysDifference = $start->diffInDays($end) + 1;
        $previousEnd = (clone $start)->subDay()->endOfDay();
        $previousStart = (clone $previousEnd)->subDays($daysDifference - 1)->startOfDay();

        $currentQuery = Order::query()
            ->where('tenant_id', Filament::getTenant()->id)
            ->where('orders.status', 'completed')
            ->whereBetween('created_at', [$start, $end]);

        $currentOrders = $currentQuery->count();
        $currentProductsSold = (clone $currentQuery)
            ->join('order_product', 'orders.id', '=', 'order_product.order_id')
            ->sum('order_product.quantity');
        $currentRevenue = (clone $currentQuery)->sum('total_price');

        $currentAov = $currentOrders > 0 ? $currentRevenue / $currentOrders : 0;

        $previousQuery = Order::query()
            ->where('tenant_id', Filament::getTenant()->id)
            ->where('orders.status', 'completed')
            ->whereBetween('created_at', [$previousStart, $previousEnd]);

        $previousOrders = $previousQuery->count();
        $previousProductsSold = (clone $previousQuery)
            ->join('order_product', 'orders.id', '=', 'order_product.order_id')
            ->sum('order_product.quantity');
        $previousRevenue = (clone $previousQuery)->sum('total_price');

        $previousAov = $previousOrders > 0 ? $previousRevenue / $previousOrders : 0;

        $buildStat = function ($label, $current, $previous, $prefix = '') use ($daysDifference, $previousStart, $previousEnd) {
            $formattedCurrent = $prefix . number_format((float) $current, 0, ',', '.');
            $stat = Stat::make($label, $formattedCurrent);

            if ($daysDifference === 1) {
                $dateLabel = $previousStart->locale('id')->translatedFormat('j M');
            } else {
                $dateLabel = $previousStart->locale('id')->translatedFormat('j M') . ' - ' . $previousEnd->locale('id')->translatedFormat('j M');
            }

            if ($previous == 0 && $current == 0) {
                return $stat->description("0% vs {$dateLabel}")->color('gray');
            }
            if ($previous == 0 && $current > 0) {
                return $stat->description("100% vs {$dateLabel}")
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success');
            }

            $percentage = (($current - $previous) / $previous) * 100;
            $formattedPercentage = number_format(abs($percentage), 0, ',', '.') . '%';

            if ($percentage > 0) {
                return $stat->description("{$formattedPercentage} vs {$dateLabel}")
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success');
            } elseif ($percentage < 0) {
                return $stat->description("{$formattedPercentage} vs {$dateLabel}")
                    ->descriptionIcon('heroicon-m-arrow-trending-down')
                    ->color('danger');
            }

            return $stat->description("0% vs {$dateLabel}")->color('gray');
        };

        return [
            $buildStat('Total Transaksi', $currentOrders, $previousOrders),
            $buildStat('Produk Terjual', $currentProductsSold, $previousProductsSold),
            $buildStat('Total Pemasukan', $currentRevenue, $previousRevenue, 'Rp '),
            $buildStat('Rata-rata Nilai Transaksi', $currentAov, $previousAov, 'Rp '),
        ];
    }
}
