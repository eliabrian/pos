<?php

namespace App\Livewire;

use App\Models\Product;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductSummary extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
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

        return $table
            ->deferLoading()
            ->heading('Berdasarkan Produk Terjual')
            ->query(
                Product::query()
                    ->select('products.*')
                    ->where('tenant_id', Filament::getTenant()->id)
            )
            ->modifyQueryUsing(function (Builder $query) use ($end, $start) {

                $applyDates = function ($q) use ($end, $start) {
                    $q->whereBetween('orders.created_at', [$start, $end]);
                };

                $query->addSelect([
                    'total_sold' => DB::table('order_product')
                        ->join('orders', 'orders.id', '=', 'order_product.order_id')
                        ->whereColumn('order_product.product_id', 'products.id')
                        ->where('orders.status', 'completed')
                        ->where($applyDates)
                        ->selectRaw('COALESCE(SUM(order_product.quantity), 0)'),

                    'total_price' => DB::table('order_product')
                        ->join('orders', 'orders.id', '=', 'order_product.order_id')
                        ->whereColumn('order_product.product_id', 'products.id')
                        ->where('orders.status', 'completed')
                        ->where($applyDates)
                        ->selectRaw('COALESCE(SUM(order_product.sub_total), 0)'),
                ]);
            })
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->sortable(),

                TextColumn::make('total_sold')
                    ->label('Kuantitas Terjual')
                    ->sortable(),

                TextColumn::make('total_price')
                    ->label('Total Penjualan')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
            ])
            ->defaultSort('total_sold', 'desc');
    }
}
