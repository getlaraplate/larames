<?php

declare(strict_types=1);

namespace Modules\MES\Filament\Resources\MachineSources\RelationManagers;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Core\Models\User;
use Modules\MES\Machine\MachineAgentConfigService;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineSource;
use Override;
use stdClass;

/**
 * The `laraplate-edge-config/1` versions of a source: publish a document, see which version is active, activate an
 * older one.
 *
 * Every write goes through {@see MachineAgentConfigService}, the only writer of these records. Versions only grow:
 * activating an older one publishes its document again as a new version. The backend numbers the versions, so the
 * `version` an operator writes in the editor is dropped before the document is validated and published.
 */
final class AgentConfigsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'agentConfigs';

    #[Override]
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedDocumentText;

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('mes.entities.machine_agent_configs.plural');
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return false;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')
                    ->label(__('mes.fields.version'))
                    ->prefix('v')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('mes.fields.active_version'))
                    ->boolean()
                    ->state(fn (MachineAgentConfig $record): bool => $record->getKey() === $this->source()->active_config_id),
                TextColumn::make('author.name')
                    ->label(__('mes.fields.author'))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('mes.fields.published_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                $this->publishAction(),
            ])
            ->recordActions([
                $this->viewDocumentAction(),
                $this->activateAction(),
            ]);
    }

    /**
     * The document as the editor shows it: indented, with empty objects kept as `{}`.
     */
    private static function encode(stdClass $document): string
    {
        return (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The document the service gets for the text of the editor: the tree without its `version`, which the backend
     * writes. Text that is not a JSON object is passed on as it is, for the service to describe.
     */
    private static function documentFor(string $text): stdClass|string
    {
        $tree = json_decode($text, false);

        if (! $tree instanceof stdClass) {
            return $text;
        }

        unset($tree->version);

        return $tree;
    }

    /**
     * Rule of the editor: the messages of the service become the errors of the field.
     */
    private static function documentRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            foreach (resolve(MachineAgentConfigService::class)->validate(self::documentFor($value)) as $message) {
                $fail($message);
            }
        };
    }

    private function source(): MachineSource
    {
        $source = $this->getOwnerRecord();

        if (! $source instanceof MachineSource) {
            throw new LogicException('The agent configurations belong to a machine source.');
        }

        return $source;
    }

    private function mayChangeSource(): bool
    {
        return Gate::allows('update', $this->source());
    }

    private function publishAction(): Action
    {
        return Action::make('publish')
            ->label(__('mes.actions.publishConfiguration'))
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (): bool => $this->mayChangeSource())
            ->fillForm(function (): array {
                $active = $this->source()->activeConfig;

                return ['document' => $active instanceof MachineAgentConfig ? self::encode($active->document) : null];
            })
            ->schema([
                CodeEditor::make('document')
                    ->label(__('mes.fields.document'))
                    ->language(Language::Json)
                    ->helperText(__('mes.help.agentConfigDocument'))
                    ->required()
                    ->rule(static fn (): Closure => self::documentRule()),
            ])
            ->action(function (array $data): void {
                $source = $this->source();
                $text = is_string($data['document'] ?? null) ? $data['document'] : '';

                try {
                    $config = resolve(MachineAgentConfigService::class)->publish($source, self::documentFor($text), $this->author());
                } catch (ValidationException $validationException) {
                    Notification::make()
                        ->title(__('mes.messages.configurationNotPublished'))
                        ->body(implode("\n", $validationException->errors()['document'] ?? []))
                        ->danger()
                        ->send();

                    return;
                }

                $source->refresh();

                Notification::make()->title(__('mes.messages.configurationPublished', ['version' => $config->version]))->success()->send();
            });
    }

    private function activateAction(): Action
    {
        return Action::make('activate')
            ->label(__('mes.actions.activateConfiguration'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->requiresConfirmation()
            ->modalDescription(__('mes.help.activateConfiguration'))
            ->visible(fn (MachineAgentConfig $record): bool => $record->getKey() !== $this->source()->active_config_id && $this->mayChangeSource())
            ->action(function (MachineAgentConfig $record): void {
                try {
                    $config = resolve(MachineAgentConfigService::class)->activate($record, $this->author());
                } catch (ValidationException $validationException) {
                    Notification::make()
                        ->title(__('mes.messages.configurationNotPublished'))
                        ->body(implode("\n", $validationException->errors()['document'] ?? []))
                        ->danger()
                        ->send();

                    return;
                }

                $this->source()->refresh();

                Notification::make()->title(__('mes.messages.configurationActivated', ['version' => $config->version]))->success()->send();
            });
    }

    private function viewDocumentAction(): Action
    {
        return Action::make('view_document')
            ->label(__('mes.actions.viewDocument'))
            ->icon(Heroicon::OutlinedEye)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitAction(false)
            ->fillForm(static fn (MachineAgentConfig $record): array => ['document' => self::encode($record->document)])
            ->schema([
                CodeEditor::make('document')
                    ->label(__('mes.fields.document'))
                    ->language(Language::Json)
                    ->disabled(),
            ]);
    }

    private function author(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
