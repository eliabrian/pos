<?php

namespace App\Filament\Imports;

use App\Models\Category;
use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nama')
                ->requiredMapping()
                ->rules([
                    'required',
                    'max:255',
                ])
                ->example('Chicken Yakiniku'),

            ImportColumn::make('category')
                ->relationship(resolveUsing: function (string $state, ImportColumn $column): ?Category {
                    $tenantId = $column->getImporter()->getOptions()['tenant_id'];

                    return Category::query()
                        ->where('tenant_id', $tenantId)
                        ->whereLike('name', "%{$state}%")
                        ->first();
                })
                ->label('Kategory')
                ->requiredMapping()
                ->rules([
                    'required',
                    'exists:categories,name'
                ])
                ->example('Rice Bowl'),

            ImportColumn::make('sku')
                ->label('SKU')
                ->requiredMapping()
                ->example('PROD-001'),

            ImportColumn::make('description')
                ->label('Deskripsi')
                ->requiredMapping()
                ->example('Ayam goreng lembut yang dibalut saus yakiniku manis, menawarkan perpaduan sempurna antara rasa manis dan gurih dengan aroma wijen yang menggoda.'),
            
            ImportColumn::make('price')
                ->label('Harga Awal')
                ->requiredMapping()
                ->rules([
                    'numeric',
                    'min:0',
                ])
                ->example('49000'),

            ImportColumn::make('discount')
                ->label('Diskon')
                ->requiredMapping()
                ->rules([
                    'integer',
                    'min:0',
                    'max:100',
                ])
                ->example('10')
        ];
    }

    public function resolveRecord(): Product
    {
        $product = Product::firstOrNew([
            'sku' => $this->data['sku'],
        ]);

        $reduced = $product->price * ($product->discount / 100);
        $product->discount = $product->price - $reduced;

        $product->tenant_id = $this->options['tenant_id'];

        return $product;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Unggah produk telah berhasil dan ' . Number::format($import->successful_rows) . ' ' . ' data telah terunggah.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . ' data gagal di unggah.';
        }

        return $body;
    }
}
