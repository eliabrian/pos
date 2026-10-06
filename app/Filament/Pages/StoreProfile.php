<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class StoreProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.store-profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Kelola Pemesanan QR';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Profil Toko';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getTenant()->hasFeature('has_qr_order') && !Auth::user()->isSystemAdmin();
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        $this->form->fill($tenant->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Form::make([
                    Section::make('Informasi Dasar')
                        ->description('Informasi ini akan terlihat oleh kustomer ketika mereka membuka halaman Pemesanan QR')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama Toko')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('phone')
                                ->label('Telepon')
                                ->placeholder('+62-812-3456-7890'),

                            TextInput::make('business_email')
                                ->label('Email')
                                ->maxLength('255')
                                ->email()
                                ->placeholder('example@email.com'),

                            Textarea::make('address')
                                ->label('Alamat Lengkap')
                                ->rows(3)
                                ->maxLength(500),
                        ]),

                    Section::make('Branding Toko')
                        ->schema([
                            FileUpload::make('logo')
                                ->image()
                                ->afterLabel('(Maks. 2MB)')
                                ->maxSize(2048)
                                ->visibility('public')
                                ->directory('tenant-logos')
                                ->imageEditor()
                                ->deleteUploadedFileUsing(function ($file) {
                                    $this->data['logo'] = null;
                                    $tenant = Filament::getTenant();
                                    $tenant->update($this->data);
                                    $tenant->save();

                                    Storage::disk('public')->delete($file);
                                }),

                            FileUpload::make('cover')
                                ->image()
                                ->afterLabel('(Maks. 2MB)')
                                ->maxSize(2048)
                                ->visibility('public')
                                ->directory('tenant-covers')
                                ->imageEditor()
                                ->deleteUploadedFileUsing(function ($file) {
                                    $this->data['cover'] = null;
                                    $tenant = Filament::getTenant();
                                    $tenant->update($this->data);
                                    $tenant->save();

                                    Storage::disk('public')->delete($file);
                                }),
                        ]),

                    Section::make('Detail Toko')
                        ->schema([
                            Repeater::make('open_hours')
                                ->label('Jam Buka')
                                ->addActionLabel('Tambah Jam Buka')
                                ->defaultItems(0)
                                ->compact()
                                ->reorderable(false)
                                ->table([
                                    TableColumn::make('Hari'),
                                    TableColumn::make('Buka'),
                                    TableColumn::make('Tutup'),
                                ])
                                ->schema([
                                    Select::make('day')
                                        ->options([
                                            'Senin' => 'Senin',
                                            'Selasa' => 'Selasa',
                                            'Rabu' => 'Rabu',
                                            'Kamis' => 'Kamis',
                                            'Jumat' => 'Jumat',
                                            'Sabtu' => 'Sabtu',
                                            'Minggu' => 'Minggu',
                                        ]),

                                    TimePicker::make('open')
                                        ->seconds(false)
                                        ->required(),

                                    TimePicker::make('close')
                                        ->seconds(false)
                                        ->required(),
                                ])
                        ]),
                ])
                ->livewireSubmitHandler('save')
                ->footer([
                    Action::make('save')
                        ->submit('save')
                        ->label('Simpan'),
                ])
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = Filament::getTenant();
        $tenant->update($data);

        Notification::make()
            ->success()
            ->title('Data berhasil disimpan')
            ->send();
    }
}
