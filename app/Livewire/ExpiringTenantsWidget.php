<?php

namespace App\Livewire;

use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ExpiringTenantsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Attention Required: Expiring in 7 Days';

    public static function canView(): bool
    {
        return Auth::user()->isSystemAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Subscription::query()
                    ->with(['tenant', 'plan'])
                    ->where('status', 'active')
                    ->where(function (Builder $query) {
                        $now = now();
                        $nextWeek = now()->addDays(7);
                        $query->whereBetween('ends_at', [$now, $nextWeek])
                              ->orWhereBetween('trial_ends_at', [$now, $nextWeek]);
                    })
                    ->latest('updated_at')
            )
            ->columns([
                TextColumn::make('tenant.name')
                    ->label('Tenant Name')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('type')
                    ->label('Event')
                    ->getStateUsing(fn (Subscription $record): string => $record->ends_at ? 'Renewal Due' : 'Trial Ending')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Renewal Due' ? 'info' : 'warning'),

                TextColumn::make('expiration_date')
                    ->label('Expires On')
                    ->getStateUsing(fn (Subscription $record) => $record->ends_at ?? $record->trial_ends_at)
                    ->dateTime('d F Y')
                    ->color('danger'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('Contact')
                    ->icon(Heroicon::ChatBubbleLeftEllipsis)
                    ->url(function (Subscription $record) {
                        $shopName = urlencode($record->tenant->name);
                        $event = $record->ends_at ? 'subscription renewal' : 'free trial';

                        $message = "Hi {$shopName} team! Just a quick reminder that your POS {$event} is coming up. Let us know if you need help with anything!";

                        return "https://wa.me/?text={$message}";
                    })
                    ->openUrlInNewTab(),

                EditAction::make()
                    ->url(fn (Subscription $record) => route('filament.admin.resources.tenants.edit', ['tenant' => Filament::getTenant()->slug, 'record' => $record->tenant_id]))
                    ->label('Manage'),
            ])
            ->emptyStateHeading('No expiring subscriptions');
    }
}
