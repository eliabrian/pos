<?php

namespace App\Filament\Clusters\Sales\Pages;

use App\Filament\Clusters\Sales\SalesCluster;
use App\Filament\Exports\SalesCategoryExporter;
use App\Models\Category;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class SalesCategory extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.clusters.sales.pages.sales-category';

    protected static ?string $cluster = SalesCluster::class;

    protected static ?string $title = 'Laporan Penjualan Kategori';

    protected static ?string $navigationLabel = 'Kategori';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Category::query()
                    ->select('categories.*')
                    ->where('tenant_id', Filament::getTenant()->id)
            )
            ->modifyQueryUsing(function (Builder $query) {
                $filterState = $this->getTableFilterState('date_range') ?? [];
                $dateString = $filterState['date_range'] ?? null;

                $applyDates = function ($q) use ($dateString) {
                    if ($dateString) {
                        $dates = explode(' - ', $dateString);

                        if (count($dates) === 2) {
                            $start = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->startOfDay();
                            $end = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->endOfDay();
                            $q->whereBetween('orders.created_at', [$start, $end]);
                        }
                    }
                };

                $query->addSelect([
                    'total_sold' => DB::table('order_product')
                        ->join('products', 'products.id', '=', 'order_product.product_id')
                        ->join('orders', 'orders.id', '=', 'order_product.order_id')
                        ->whereColumn('products.category_id', 'categories.id')
                        ->where($applyDates)
                        ->selectRaw('COALESCE(SUM(order_product.quantity), 0)'),

                    'total_price' => DB::table('order_product')
                        ->join('products', 'products.id', '=', 'order_product.product_id')
                        ->join('orders', 'orders.id', '=', 'order_product.order_id')
                        ->whereColumn('products.category_id', 'categories.id')
                        ->where($applyDates)
                        ->selectRaw('COALESCE(SUM(order_product.sub_total), 0)'),
                ]);
            })
            ->filters([
                DateRangeFilter::make('date_range')
                    ->label('Rentang Waktu')
                    ->query(fn ($query) => $query)
                    ->defaultThisMonth(),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('total_sold')
                    ->label('Kuantitas Terjual')
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->hiddenLabel()
                            ->using(fn ($query) => $query->get()->sum('total_sold'))
                    ),

                TextColumn::make('total_price')
                    ->label('Total Penjualan')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->hiddenLabel()
                            ->money('IDR', locale: 'id')
                            ->using(fn ($query) => $query->get()->sum('total_price'))
                    ),
            ])
            ->paginated(false)
            ->headerActions([
                ExportAction::make()
                    ->label('Unduh')
                    ->icon(Heroicon::ArrowDownTray)
                    ->exporter(SalesCategoryExporter::class),
            ]);
    }
}
