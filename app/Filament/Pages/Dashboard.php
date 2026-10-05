<?php

namespace App\Filament\Pages;

use App\Livewire\ExpiringTenantsWidget;
use App\Livewire\PeakHoursChart;
use App\Livewire\PlanDistributionChart;
use App\Livewire\ProductSummary;
use App\Livewire\ShopStats;
use App\Livewire\SubscriptionMetricChart;
use App\Livewire\SubscriptionMetricWidget;
use App\Livewire\YearlyRevenueChart;
use App\Models\Category;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashbor';

    protected ?string $heading = 'Dashbor';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DateRangePicker::make('date')
                            ->defaultToday()
                            ->label('Rentang Waktu'),
                    ])
                    ->visible(function () {
                        return !Auth::user()->isSystemAdmin();
                    })
                    ->columns(['md' => 1, 'xl' => 1])
                    ->columnSpanFull(),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            SubscriptionMetricWidget::class,
            SubscriptionMetricChart::class,
            PlanDistributionChart::class,
            ExpiringTenantsWidget::class,
            ShopStats::class,
            YearlyRevenueChart::class,
            PeakHoursChart::class,
            ProductSummary::class,
        ];
    }
}
