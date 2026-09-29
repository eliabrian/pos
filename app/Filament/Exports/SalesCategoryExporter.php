<?php

namespace App\Filament\Exports;

use App\Models\Category;
use App\Models\SalesCategory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class SalesCategoryExporter extends Exporter
{
    protected static ?string $model = Category::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('Nama Kategori'),

            ExportColumn::make('total_sold')
                ->label('Kuantitas Terjual'),

            ExportColumn::make('total_price')
                ->label('Total Penjualan'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor Laporan Penjualan Kategori Anda telah selesai. ' . Str::of('row')->counted($export->successful_rows) . ' baris berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Str::of('row')->counted($failedRowsCount) . ' baris gagal diekspor.';
        }

        return $body;
    }
}
