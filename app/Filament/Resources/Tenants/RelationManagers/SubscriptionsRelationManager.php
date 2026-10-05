<?php

namespace App\Filament\Resources\Tenants\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';
    protected static ?string $recordTitleAttribute = 'id';
    protected static ?string $title = 'Billing & Subscriptions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'past_due' => 'Past Due',
                        'canceled' => 'Canceled',
                    ])
                    ->required()
                    ->default('active'),

                DateTimePicker::make('trial_ends_at')
                    ->label('Trial Ends At')
                    ->live()
                    ->helperText('Leave blank if no trial.'),

                DateTimePicker::make('starts_at')
                    ->label('Subscription Starts At')
                    ->required(fn (Get $get): bool => blank($get('trial_ends_at')))
                    ->default(now()),

                DateTimePicker::make('ends_at')
                    ->label('Next Billing Date (Ends At)')
                    ->required(fn (Get $get): bool => blank($get('trial_ends_at')))
                    ->helperText('POS will lock if this date passes and they haven\'t paid.'),

                DateTimePicker::make('canceled_at')
                    ->label('Canceled At'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('plan.name')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'past_due' => 'warning',
                        'canceled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('trial_ends_at')
                    ->dateTime('M d, Y')
                    ->placeholder('-'),

                TextColumn::make('starts_at')
                    ->dateTime('M d, Y')
                    ->placeholder('-'),

                TextColumn::make('ends_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->color(fn ($record) => $record->ends_at?->isPast() ? 'danger' : 'success')
                    ->weight(fn ($record) => $record->ends_at?->isPast() ? 'bold' : 'normal')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'past_due' => 'Past Due',
                        'canceled' => 'Canceled',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalHeading('Create New Subscription'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
