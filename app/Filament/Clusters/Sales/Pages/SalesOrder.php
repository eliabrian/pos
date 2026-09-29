<?php

namespace App\Filament\Clusters\Sales\Pages;

use App\Filament\Clusters\Sales\SalesCluster;
use App\Filament\Exports\SalesOrderExporter;
use App\Models\Order;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Malzariey\FilamentDaterangepickerFilter\Enums\OpenDirection;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class SalesOrder extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.clusters.sales.pages.sales-order';

    protected static ?string $cluster = SalesCluster::class;

    protected static ?string $title = 'Laporan Penjualan';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 0;

    public function table(Table $table): Table
    {
        $groupedQuery = DB::table('orders')
            ->select([
                DB::raw("MAX(DATE_FORMAT(orders.created_at, '%Y%m%d')) as id"),
                'orders.tenant_id',
                DB::raw('DATE(orders.created_at) as date'),
                DB::raw('COUNT(orders.id) as total_transactions'),
                DB::raw('SUM(orders.total_price) as total_sales'),
                DB::raw('COALESCE(SUM(op.total_qty), 0) as total_products_sold'),
            ])
            ->leftJoinSub(
                DB::table('order_product')
                    ->selectRaw('order_id, SUM(quantity) as total_qty')
                    ->groupBy('order_id'),
                'op',
                'op.order_id', '=', 'orders.id'
            )
            ->groupByRaw('DATE(orders.created_at), orders.tenant_id');

        return $table
            ->query(
                Order::query()->fromSub($groupedQuery, 'orders')
            )
            ->defaultSort('date', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(SalesOrderExporter::class)
                    ->label('Unduh')
                    ->icon(Heroicon::ArrowDownTray),
            ])
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->locale('id')->translatedFormat('l, d F Y'))
                    ->sortable(),

                TextColumn::make('total_transactions')
                    ->label('Jumlah Transaksi')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->hiddenLabel()),

                TextColumn::make('total_products_sold')
                    ->label('Produk Terjual')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->hiddenLabel()),

                TextColumn::make('total_sales')
                    ->label('Total Penjualan')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->summarize(Sum::make()->hiddenLabel()->money('IDR')),
            ])
            ->filters([
                DateRangeFilter::make('date')
                    ->label('Rentang Waktu')
                    ->defaultThisMonth(),
            ]);
    }
}
