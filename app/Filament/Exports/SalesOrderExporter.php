<?php

namespace App\Filament\Exports;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class SalesOrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('date')
                ->label('Tanggal')
                ->formatStateUsing(fn ($state) => Carbon::parse($state)->locale('id')->translatedFormat('l, d F Y')),

            ExportColumn::make('total_transactions')
                ->label('Jumlah Transaksi'),

            ExportColumn::make('total_products_sold')
                ->label('Produk Terjual'),

            ExportColumn::make('total_sales')
                ->label('Total Penjualan'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor Laporan Penjualan Anda telah selesai. ' . Str::of('row')->counted($export->successful_rows) . ' baris berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Str::of('row')->counted($failedRowsCount) . ' baris gagal diekspor.';
        }

        return $body;
    }
}
