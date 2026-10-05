<?php

namespace App\Filament\Resources\VenueTables;

use App\Filament\Resources\VenueTables\Pages\ManageVenueTables;
use App\Models\VenueTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Linkxtr\QrCode\Facades\QrCode;
use UnitEnum;
use ZipArchive;

class VenueTableResource extends Resource
{
    protected static ?string $model = VenueTable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Meja';

    protected static ?string $label = 'Meja';

    protected static string|UnitEnum|null $navigationGroup = 'Kelola Pemesanan QR';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Meja')
                    ->searchable(),

                TextColumn::make('qr_token')
                    ->label('Tautan')
                    ->icon(Heroicon::ArrowTopRightOnSquare)
                    ->url(function ($record) {
                        $tenantSlug = Filament::getTenant()->slug;
                        return url("/order/{$tenantSlug}/{$record->qr_token}");
                    }, true)
                    ->formatStateUsing(function ($state) {
                        $tenantSlug = Filament::getTenant()->slug;
                        return url("/order/{$tenantSlug}/{$state}");
                    })
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('show_qr')
                    ->label('Lihat QR')
                    ->color('gray')
                    ->icon(Heroicon::QrCode)
                    ->modalHeading(fn (VenueTable $record) => 'QR Code: ' . $record->name)
                    ->modalContent(function (VenueTable $record) {
                        $tenantSlug = Filament::getTenant()->slug;
                        $url = url("/order/{$tenantSlug}/{$record->qr_token}");
                        $qrCode = QrCode::size(500)
                            ->format('png')
                            ->margin(1)
                            ->generate($url);

                        $base64Image = 'data:image/png;base64,' . base64_encode($qrCode);

                        return new HtmlString('
                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                <div style="background-color: white; border-radius: 0.75rem;">
                                    <img src="' . $base64Image . '" alt="QR Code" style="width: 250px; height: 250px; display: block;" />
                                </div>
                            </div>
                        ');
                    })
                    ->modalAlignment(Alignment::Center)
                    ->modalWidth(Width::Medium)
                    ->modalFooterActionsAlignment(Alignment::Center)
                    ->modalSubmitActionLabel('Unduh')
                    ->modalCancelActionLabel('Tutup')
                    ->action(function (VenueTable $record) {
                        $tenantSlug = Filament::getTenant()->slug;
                        $url = url("/order/{$tenantSlug}/{$record->qr_token}");
                        $qrCode = QrCode::size(500)
                            ->format('png')
                            ->margin(1)
                            ->generate($url);

                        $safeName = Str::slug($record->name) . '-qr.png';

                        return response()->streamDownload(function () use ($qrCode) {
                            echo $qrCode;
                        }, $safeName, [
                            'Content-Type' => 'image/png',
                        ]);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('download_qr')
                        ->label('Unduh')
                        ->icon(Heroicon::ArrowDownTray)
                        ->color('primary')
                        ->action(function (Collection $records) {
                            $zip = new ZipArchive();
                            $fileName = 'QR_Table_' . now()->format('Ymd_His') . '.zip';
                            $tenantSlug = Filament::getTenant()->slug;

                            $zipPath = storage_path('app/' . $fileName);

                            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                                foreach ($records as $record) {
                                    $url = url("/order/{$tenantSlug}/{$record->qr_token}");

                                    $qrCode = QrCode::size(500)
                                        ->format('png')
                                        ->margin(1)
                                        ->generate($url);

                                    $safeName = Str::slug($record->name) . '-qr.png';

                                    $zip->addFromString($safeName, $qrCode);
                                }
                                $zip->close();
                            }

                            return response()->download($zipPath)->deleteFileAfterSend(true);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVenueTables::route('/'),
        ];
    }
}
