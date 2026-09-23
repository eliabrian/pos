<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('void_order')
                ->label('Batalkan Transaksi (Void)')
                ->icon(Heroicon::ArchiveBoxXMark)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Transaksi Ini?')
                ->modalDescription('Tindakan ini akan mengubah status transaksi menjadi Void dan mengembalikan stok produk. Tindakan ini tidak dapat dibatalkan.')
                ->modalSubmitActionLabel('Ya, Void Transaksi')
                ->visible(function ($record) {
                    if ($record->status !== 'completed') {
                        return false;
                    }

                    $userRole = Auth::user()
                        ->tenants()
                        ->whereKey($record->tenant_id)
                        ->first()
                        ?->pivot->role;

                    return in_array($userRole, ['owner', 'backoffice']);
                })
                ->action(function ($record) {
                    DB::transaction(function () use ($record) {
                        $record->update(['status' => 'void']);

                        foreach ($record->products as $product) {
                            $quantitySold = $product->pivot->quantity;
                            $product->increment('stock', $quantitySold);
                        }

                        Notification::make()
                            ->title('Transaksi Dibatalkan')
                            ->body('Status pesanan menjadi Void dan stok telah dikembalikan.')
                            ->success()
                            ->send();
                    });
                })
        ];
    }
}
