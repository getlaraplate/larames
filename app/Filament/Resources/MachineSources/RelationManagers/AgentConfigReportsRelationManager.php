<?php

declare(strict_types=1);

namespace Modules\MES\Filament\Resources\MachineSources\RelationManagers;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Override;

/**
 * What the edge agent of a source reported about each configuration version it tried to apply, newest first.
 *
 * Read-only: the agent writes the reports through the report route.
 */
final class AgentConfigReportsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'agentConfigReports';

    #[Override]
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('mes.entities.machine_agent_config_reports.plural');
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return true;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('config_version')
            ->defaultSort(static fn (Builder $query): Builder => $query->orderByDesc('reported_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('config_version')
                    ->label(__('mes.fields.version'))
                    ->prefix('v'),
                TextColumn::make('outcome')
                    ->label(__('mes.fields.outcome'))
                    ->badge()
                    ->color(static fn (MachineAgentConfigOutcome $state): string => match ($state) {
                        MachineAgentConfigOutcome::Applied => 'success',
                        MachineAgentConfigOutcome::Rejected => 'danger',
                        MachineAgentConfigOutcome::RolledBack => 'warning',
                    }),
                TextColumn::make('errors')
                    ->label(__('mes.fields.errors'))
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('agent_version')
                    ->label(__('mes.fields.agent_version')),
                TextColumn::make('reported_at')
                    ->label(__('mes.fields.reported_at'))
                    ->dateTime(),
            ]);
    }
}
