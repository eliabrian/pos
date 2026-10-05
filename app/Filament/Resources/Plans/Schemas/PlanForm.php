<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation == 'create' ? $set('slug', Str::slug($state)) : null),

                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pricing & Status')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->default(0),

                        Select::make('interval')
                            ->options([
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                            ])
                            ->required()
                            ->default('month'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Turn off to hide this plan from new customers.'),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Order displayed on the pricing page (lower is first).'),
                    ]),

                Section::make('Features (JSON)')
                    ->columnSpanFull()
                    ->description('Define what this plan can do. These act as gatekeepers in the POS.')
                    ->schema([
                        KeyValue::make('features')
                            ->hiddenLabel()
                            ->keyLabel('Feature Key')
                            ->valueLabel('Limit / Value')
                            ->addActionLabel('+ Add Feature Limit')
                    ])
            ]);
    }
}
