<?php

declare(strict_types=1);

namespace Modules\MES\Filament\Resources\MachineSources\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Models\User;
use Modules\MES\Models\MachineSource;
use Modules\MES\Enums\MachineTransport;
use Modules\MES\Machine\Normalizers\NormalizerRegistry;

final class MachineSourceForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('user_id')
                    ->label(__('mes.fields.service_account'))
                    ->relationship('serviceAccount', 'name', self::onlyServiceAccounts(...))
                    ->rules([Rule::exists(CoreTables::Users->value, 'id')->where('is_service_account', true)])
                    ->unique(ignoreRecord: true)
                    ->helperText(__('mes.help.serviceAccount'))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('code')
                    ->required()
                    ->maxLength(64),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('normalizer')
                    ->options(static fn (): array => array_combine(resolve(NormalizerRegistry::class)->keys(), resolve(NormalizerRegistry::class)->keys()))
                    ->default('canonical')
                    ->required(),
                Select::make('transport')
                    ->options(array_combine(MachineTransport::values(), MachineTransport::values()))
                    ->default(MachineTransport::Http->value)
                    ->live()
                    ->required(),
                TextInput::make('mqtt_topic')
                    ->maxLength(255)
                    ->visible(static fn ($get): bool => MachineTransport::Mqtt->value === $get('transport')),
                KeyValue::make('normalizer_options')
                    ->helperText(__('mes.help.normalizerOptions')),
                TextInput::make('heartbeat_timeout_seconds')
                    ->numeric()
                    ->minValue(1)
                    ->default(120)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    /**
     * The users a source may authenticate as. The condition goes on the base query: `is_service_account` comes from a
     * Core module migration, which the static analyser's schema of the users table does not read.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function onlyServiceAccounts(Builder $query, ?Model $record = null): Builder
    {
        $query->getQuery()
            ->where($query->getModel()->qualifyColumn('is_service_account'), true)
            // An account speaks for one source only: offer the ones no other source uses.
            ->whereNotIn($query->getModel()->qualifyColumn('id'), MachineSource::query()->withoutGlobalScopes()->whereNotNull('user_id')->when($record?->getKey(), static fn ($sources, mixed $key) => $sources->whereKeyNot($key))->select('user_id')->toBase());

        return $query;
    }
}
