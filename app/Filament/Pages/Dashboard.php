<?php

namespace App\Filament\Pages;

use App\Livewire\PeakHoursChart;
use App\Livewire\ProductSummary;
use App\Livewire\ShopStats;
use App\Livewire\YearlyRevenueChart;
use App\Models\Category;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashbor Toko';

    protected ?string $heading = 'Dashbor Toko';

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
                    ->columns(['md' => 1, 'xl' => 1])
                    ->columnSpanFull(),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            ShopStats::class,
            YearlyRevenueChart::class,
            PeakHoursChart::class,
            ProductSummary::class,
        ];
    }
}
