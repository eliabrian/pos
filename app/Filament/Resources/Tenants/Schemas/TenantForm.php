<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Informasi Tenant')
                    ->columns(2)
                    ->columnSpan(fn () => Auth::user()->isSystemAdmin() ? 2 : 3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Tenant')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('Domain Tenant')
                            ->required()
                            ->maxLength(255)
                            ->prefix('https://')
                            ->suffix('pos.test')
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (): bool => ! Auth::user()->isSystemAdmin()),

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
                            ->maxLength('255')
                            ->columnSpanFull(),
                    ]),

                Section::make('Tenant Status')
                    ->visible(fn () => Auth::user()->isSystemAdmin())
                    ->schema([
                        Select::make('plan')
                            ->options([
                                'trial' => 'Trial',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->native(false)
                            ->default('trial')
                            ->required(),
                    ]),

                Section::make('Kredensial Payment Gateway')
                    ->icon(Heroicon::Key)
                    ->columnSpan(fn () => Auth::user()->isSystemAdmin() ? 2 : 3)
                    ->schema([
                        TextInput::make('qris_client_id')
                            ->label('QRIS Client ID')
                            ->maxLength(255),

                        TextInput::make('payment_client_id')
                            ->label('Client ID')
                            ->maxLength(255),

                        TextInput::make('payment_api_key')
                            ->label('API Key')
                            ->maxLength(255),

                        TextInput::make('payment_secret_key')
                            ->label('Secret Key')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->requiredWith('payment_client_id'),

                        Hidden::make('rsa_private_key'),

                        Textarea::make('rsa_public_key')
                        ->label('Public Key')
                        ->readOnly()
                        ->rows(8)
                        ->hintAction(
                            Action::make('generate_rsa')
                                ->label('Generate Kunci Baru')
                                ->icon('heroicon-m-arrow-path')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->modalHeading('Buat Pasangan Kunci RSA Baru?')
                                ->modalDescription('Ini akan menimpa kunci lama Anda. Jika kunci diubah, Anda wajib meng-upload Public Key baru ini ke dashboard DOKU agar pembayaran tetap berfungsi.')
                                ->action(function (Set $set) {

                                    $config = [
                                        "digest_alg" => "sha256",
                                        "private_key_bits" => 2048,
                                        "private_key_type" => OPENSSL_KEYTYPE_RSA,
                                        "config" => "C:/Users/it.developer/.config/herd/bin/php84/extras/ssl/openssl.cnf"
                                    ];

                                    $res = openssl_pkey_new($config);

                                    openssl_pkey_export($res, $privateKey, null, $config);

                                    $publicKey = openssl_pkey_get_details($res)["key"];

                                    $set('rsa_private_key', $privateKey);
                                    $set('rsa_public_key', $publicKey);

                                    Notification::make()
                                        ->title('Kunci RSA berhasil dibuat!')
                                        ->body('Jangan lupa klik Simpan, lalu copy Public Key ke Dashboard DOKU.')
                                        ->success()
                                        ->send();
                                })
                        ),

                    ]),
            ]);
    }
}
