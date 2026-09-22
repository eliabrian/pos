<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make('Detail Produk')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama Produk')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Deskripsi Produk')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),

                    Section::make('Harga Produk')
                        ->columns(3)
                        ->schema([
                            TextInput::make('price')
                                ->label('Harga Dasar')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Rp')
                                ->columnSpanFull()
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->placeholder(0)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    $discount = $get('discount');
                                    $reduced = $state * ($discount / 100);
                                    $finalPrice = $state - $reduced;
                                    $set('final_price', $finalPrice);
                                }),

                            TextInput::make('discount')
                                ->label('Diskon')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->columnSpan(1)
                                ->placeholder(0)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    $price = $get('price');
                                    $reduced = $price * ($state / 100);
                                    $finalPrice = $price - $reduced;
                                    $set('final_price', $finalPrice);
                                }),

                            TextInput::make('final_price')
                                ->label('Harga Akhir')
                                ->numeric()
                                ->minValue(0)
                                ->columnSpan(2)
                                ->prefix('Rp')
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->placeholder(0)
                                ->disabled(),
                        ]),

                    Section::make('Varian Produk')
                        ->schema([
                            static::getVariantsRepeater(),
                        ]),

                    Section::make('Inventaris')
                        ->columns(2)
                        ->schema([
                            TextInput::make('sku')
                                ->label('SKU (Stock Keeping Unit)')
                                ->maxLength(255),

                            TextInput::make('stock')
                                ->numeric()
                                ->placeholder(0)
                                ->minValue(0)
                        ])
                ])
                ->columnSpan(['xl' => 2, 'lg' => 'full', 'md' => 'full']),

                Group::make([
                    Section::make('Asosiasi Produk')
                        ->schema([
                            Select::make('category')
                                ->label('Kategori')
                                ->relationship('category', 'name', modifyQueryUsing: fn (Builder $query) => $query->visible()->orderBy('sort'))
                                ->required()
                                ->selectablePlaceholder(false)
                                ->native(false)
                                ->preload()
                                ->searchable()
                                ->exists('categories', 'id'),

                            Toggle::make('is_visible')
                                ->label('Visibilitas')
                                ->default(true)
                                ->helperText('Menentukan apakah produk ini ditampilkan di saluran penjualan.'),
                        ]),

                    Section::make('Foto Produk')
                        ->schema([
                            FileUpload::make('image')
                                ->hiddenLabel(true)
                                ->label('Foto Produk')
                                ->afterLabel('(Maks. 2MB)')
                                ->columnSpanFull()
                                ->image()
                                ->maxSize(2048)
                                ->imageEditor()
                                ->visibility('public')
                                ->directory('product-images')
                                ->deleteUploadedFileUsing(function ($file, $record) {
                                    $record->image = null;
                                    $record->save();

                                    Storage::disk('public')->delete($file);
                                }),
                        ]),
                ])
                ->columnSpan(['xl' => 1, 'lg' => 'full', 'md' => 'full']),
            ]);
    }

    public static function getVariantsRepeater(): Repeater
    {
        return Repeater::make('variants')
            ->columns(2)
            ->hiddenLabel()
            ->relationship('variants')
            ->addActionLabel('Tambah Tipe Varian')
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
            ->schema([
                TextInput::make('name')
                    ->hiddenLabel()
                    ->placeholder('Masukkan nama varian di sini...')
                    ->required()
                    ->columnSpanFull(),

                Toggle::make('is_required')
                    ->label('Wajib Dipilih'),

                Toggle::make('allow_multiple')
                    ->label('Pilihan Lebih Dari Satu?')
                    ->live(),

                Repeater::make('variant_items')
                    ->label('Pilihan Varian')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->relationship('variantItems')
                    ->addActionLabel('+ Tambah Opsi')
                    ->orderColumn('sort')
                    ->compact()
                    ->table([
                        TableColumn::make('Nama'),
                        TableColumn::make('Harga Varian')
                            ->width(200),
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->required(),

                        TextInput::make('price')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),
                    ]),
            ]);
    }
}
