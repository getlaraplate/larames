<?php

declare(strict_types=1);

namespace Modules\MES\Filament\Resources\UnmappedSignals\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\MES\Enums\SignalRole;
use Modules\MES\Machine\UnmappedSignalMapper;
use Modules\MES\Models\MachineDevice;
use Modules\MES\Models\MachineSignal;
use Modules\MES\Models\QualityPlanCharacteristic;
use Modules\MES\Models\UnmappedSignal;
use Modules\MES\Models\WorkCenter;

final class UnmappedSignalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('source.name')->sortable(),
                TextColumn::make('device_external_id')->searchable()->sortable(),
                TextColumn::make('signal_key')->searchable()->sortable(),
                TextColumn::make('last_value')->limit(40),
                TextColumn::make('last_seen_at')->dateTime()->sortable(),
                TextColumn::make('seen_count')->numeric()->sortable(),
            ])
            ->recordActions([
                Action::make('map')
                    ->label(__('mes.actions.map'))
                    ->icon(Heroicon::OutlinedLink)
                    ->visible(static fn (UnmappedSignal $record): bool => ! str_contains($record->signal_key, '#') && self::canMap())
                    ->schema([
                        Select::make('work_center_id')
                            ->label(__('mes.fields.work_center_for_new_device'))
                            ->options(static fn (): array => WorkCenter::query()->get()->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(static fn (UnmappedSignal $record): bool => ! MachineDevice::query()->where('source_id', $record->source_id)->where('external_id', $record->device_external_id)->exists())
                            ->visible(static fn (UnmappedSignal $record): bool => ! MachineDevice::query()->where('source_id', $record->source_id)->where('external_id', $record->device_external_id)->exists()),
                        Select::make('role')
                            ->options(array_combine(SignalRole::values(), SignalRole::values()))
                            ->required(),
                        Select::make('data_type')
                            ->options(['number' => 'number', 'boolean' => 'boolean', 'string' => 'string'])
                            ->default('number')
                            ->required(),
                        TextInput::make('unit')->maxLength(16),
                        Textarea::make('config')
                            ->rows(4)
                            ->rule('json')
                            ->helperText(__('mes.help.signalConfigLikeForm'))
                            ->dehydrateStateUsing(static fn (mixed $state): mixed => is_string($state) && $state !== '' ? json_decode($state, true) : null),
                        Select::make('quality_plan_characteristic_id')
                            ->options(static fn (): array => QualityPlanCharacteristic::query()->get()->pluck('characteristic', 'id')->all())
                            ->searchable(),
                    ])
                    ->action(static function (UnmappedSignal $record, array $data): void {
                        try {
                            $signal = resolve(UnmappedSignalMapper::class)->map($record, $data);
                        } catch (ValidationException $validationException) {
                            Notification::make()->title(__('mes.messages.signalNotMapped'))->body(collect($validationException->errors())->flatten()->implode(' '))->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('mes.messages.signalMapped', ['key' => $signal->key]))->success()->send();
                    }),
            ]);
    }

    private static function canMap(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isSuperAdmin() || $user->hasPermissionTo(PermissionName::forClass(MachineSignal::class, 'insert')));
    }
}
