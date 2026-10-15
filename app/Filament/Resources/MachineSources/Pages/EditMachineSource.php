<?php

declare(strict_types=1);

namespace Modules\MES\Filament\Resources\MachineSources\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\MES\Filament\Concerns\ChecksMesPolicy;
use Modules\MES\Filament\Resources\MachineSources\MachineSourceResource;
use Modules\MES\Machine\MachineMessageReprocessor;
use Modules\MES\Models\MachineSource;
use Override;

final class EditMachineSource extends EditRecord
{
    use ChecksMesPolicy;
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = MachineSourceResource::class;

    /**
     * @return array<int, Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reprocess_range')
                ->label('Reprocess range')
                ->icon(Heroicon::OutlinedArrowPath)
                ->schema([
                    DateTimePicker::make('from')->required(),
                    DateTimePicker::make('to')->required()->after('from'),
                ])
                ->visible(fn (): bool => $this->policyAllows('reprocessRange', $this->record))
                ->action(function (array $data): void {
                    /** @var MachineSource $source */
                    $source = $this->record;
                    $queued = resolve(MachineMessageReprocessor::class)->reprocessRange($source, Carbon::parse($data['from']), Carbon::parse($data['to']));

                    Notification::make()->title("Messages queued for reprocessing: {$queued}")->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}
