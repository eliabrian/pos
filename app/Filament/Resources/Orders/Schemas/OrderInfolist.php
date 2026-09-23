<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pesanan')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('receipt_number')->label('No. Resi'),
                        TextEntry::make('created_at')->label('Waktu Transaksi')->dateTime('d M Y, H:i'),
                        TextEntry::make('payment_method')->label('Metode Pembayaran'),
                        TextEntry::make('total_price')->label('Total Akhir')->money('IDR')->weight('bold'),
                    ]),

                Section::make('Produk Pesanan')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('products')
                            ->hiddenLabel()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('pivot.unit_name')->label('Produk')->weight('bold'),
                                TextEntry::make('pivot.quantity')->label('Qty'),
                                TextEntry::make('pivot.unit_price')->label('Harga Satuan')->money('IDR'),
                                TextEntry::make('pivot.sub_total')->label('Sub Total')->money('IDR'),
                                TextEntry::make('pivot.notes')->label('Catatan')->default('-')->fontFamily(FontFamily::Mono),

                                RepeatableEntry::make('pivot.variant_selected')
                                    ->columnSpanFull()
                                    ->label('Varian')
                                    ->getStateUsing(function ($record) {
                                        $variants = $record->pivot->variant_selected;

                                        $variants = is_string($variants) ? json_decode($variants, true) : $variants;

                                        return is_array($variants) ? $variants : [];
                                    })
                                    ->table([
                                        TableColumn::make('Kategori'),
                                        TableColumn::make('Pilihan'),
                                        TableColumn::make('Harga Tambahan'),
                                    ])
                                    ->schema([
                                        TextEntry::make('variant_name')
                                            ->label('Kategori'),
                                        TextEntry::make('item_name')
                                            ->label('Pilihan'),
                                        TextEntry::make('price')
                                            ->label('Harga Tambahan')
                                            ->money('IDR'),
                                    ])
                                    ->hidden(fn ($state) => empty($state)),
                            ])
                    ])
            ]);
    }
}
