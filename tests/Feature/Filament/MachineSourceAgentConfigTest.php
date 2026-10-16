<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Modules\MES\Filament\Resources\MachineSources\MachineSourceResource;
use Modules\MES\Filament\Resources\MachineSources\Pages\EditMachineSource;
use Modules\MES\Filament\Resources\MachineSources\RelationManagers\AgentConfigReportsRelationManager;
use Modules\MES\Filament\Resources\MachineSources\RelationManagers\AgentConfigsRelationManager;
use Modules\MES\Machine\MachineAgentConfigService;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineAgentConfigReport;
use Modules\MES\Models\MachineSource;
use Modules\MES\Tests\Support\MesTestHelpers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    MesTestHelpers::makeCompany();

    /** @var App\Models\User $superadmin */
    $superadmin = App\Models\User::query()->create(User::factory()->raw());
    $superadmin->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $this->superadmin = $superadmin;

    $this->actingAs($superadmin);
    Filament::setCurrentPanel('admin');
});

/**
 * @param  array<string, mixed>  $extra
 */
function agentConfigText(array $extra = []): string
{
    return (string) json_encode([
        'schema' => 'laraplate-edge-config/1',
        'inputs' => [['id' => 'press-line', 'connection' => ['auth' => new stdClass()]]],
        'outputs' => [['id' => 'main']],
        ...$extra,
    ], JSON_UNESCAPED_SLASHES);
}

function agentConfigsManager(MachineSource $source): Testable
{
    return Livewire::test(AgentConfigsRelationManager::class, ['ownerRecord' => $source, 'pageClass' => EditMachineSource::class]);
}

function agentConfigReportsManager(MachineSource $source): Testable
{
    return Livewire::test(AgentConfigReportsRelationManager::class, ['ownerRecord' => $source, 'pageClass' => EditMachineSource::class]);
}

it('shows the versions and the reports on the edit page of a source', function (): void {
    $source = MachineSource::factory()->create();

    Livewire::test(EditMachineSource::class, ['record' => $source->getKey()])
        ->assertSeeLivewire(AgentConfigsRelationManager::class)
        ->assertSee(__('mes.entities.machine_agent_configs.plural'))
        ->assertSee(__('mes.entities.machine_agent_config_reports.plural'));

    expect(MachineSourceResource::getRelations())->toContain(AgentConfigsRelationManager::class, AgentConfigReportsRelationManager::class);
});

it('publishes a document as the next version, active, signed by the user, keeping empty objects', function (): void {
    $source = MachineSource::factory()->create();

    agentConfigsManager($source)
        ->callAction(TestAction::make('publish')->table(), ['document' => agentConfigText()])
        ->assertHasNoFormErrors()
        ->assertNotified();
    agentConfigsManager($source)
        ->callAction(TestAction::make('publish')->table(), ['document' => agentConfigText(['plugins' => []])])
        ->assertNotified();

    $source->refresh();
    $versions = $source->agentConfigs()->orderBy('version')->get();

    expect($versions->pluck('version')->all())->toBe([1, 2])
        ->and($source->active_config_id)->toBe($versions->last()->id)
        ->and($versions->last()->created_by)->toBe($this->superadmin->id)
        ->and($versions->last()->document->version)->toBe(2)
        ->and((string) json_encode($versions->first()->document))->toContain('"auth":{}')
        ->and((string) json_encode($versions->last()->document))->toContain('"plugins":[]');
});

it('leaves the version to the backend: a version written in the editor is ignored, even a wrong one', function (mixed $written): void {
    $source = MachineSource::factory()->create();

    agentConfigsManager($source)
        ->callAction(TestAction::make('publish')->table(), ['document' => agentConfigText(['version' => $written])])
        ->assertHasNoFormErrors();

    expect($source->agentConfigs()->pluck('version')->all())->toBe([1]);
})->with([
    'a string' => ['seven'],
    'a number' => [41],
]);

it('shows the errors of the service on the field and stores nothing for an invalid document', function (string $text, string $message): void {
    $source = MachineSource::factory()->create();

    $manager = agentConfigsManager($source)
        ->callAction(TestAction::make('publish')->table(), ['document' => $text])
        ->assertHasFormErrors(['document']);

    $errors = collect($manager->errors()->toArray())->flatten()->implode("\n");

    expect($errors)->toContain($message)
        ->and(MachineAgentConfig::query()->count())->toBe(0)
        ->and($source->fresh()->active_config_id)->toBeNull();
})->with([
    'not json' => ['{broken', 'not valid JSON'],
    'a list' => ['[]', 'document'],
    'another schema' => [agentConfigText(['schema' => 'other/9']), 'schema'],
    'an input without an id' => [agentConfigText(['inputs' => [['connection' => []]]]), 'inputs.0.id'],
    'an unknown key' => [agentConfigText(['metrics' => []]), 'metrics'],
]);

it('requires a document', function (): void {
    $source = MachineSource::factory()->create();

    agentConfigsManager($source)
        ->callAction(TestAction::make('publish')->table(), ['document' => ''])
        ->assertHasFormErrors(['document' => 'required']);
});

it('offers the active document, pretty printed with its empty objects, as the start of the next one', function (): void {
    $source = MachineSource::factory()->create();
    resolve(MachineAgentConfigService::class)->publish($source, agentConfigText());
    $active = $source->fresh()->activeConfig;

    agentConfigsManager($source->fresh())
        ->mountAction(TestAction::make('publish')->table())
        ->assertActionDataSet(['document' => json_encode($active->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]);

    expect(json_encode($active->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->toContain('"auth": {}');
});

it('lists the versions newest first with author, date and the active one marked', function (): void {
    $source = MachineSource::factory()->create();
    $service = resolve(MachineAgentConfigService::class);
    $first = $service->publish($source, agentConfigText(), $this->superadmin);
    $second = $service->publish($source->fresh(), agentConfigText(['plugins' => []]));
    $other = MachineSource::factory()->create();
    $foreign = $service->publish($other, agentConfigText());

    $manager = agentConfigsManager($source->fresh())->assertOk();

    expect($manager->instance()->getTableRecords()->modelKeys())->toBe([$second->getKey(), $first->getKey()])
        ->and($manager->instance()->getTableRecords()->modelKeys())->not->toContain($foreign->getKey());

    $manager->assertTableColumnStateSet('version', 2, $second)
        ->assertTableColumnStateSet('author.name', $this->superadmin->name, $first)
        ->assertTableColumnStateSet('is_active', true, $second)
        ->assertTableColumnStateSet('is_active', false, $first)
        ->assertTableColumnExists('created_at');
});

it('activates an older version by publishing it again as a new one', function (): void {
    $source = MachineSource::factory()->create();
    $service = resolve(MachineAgentConfigService::class);
    $first = $service->publish($source, agentConfigText());
    $service->publish($source->fresh(), agentConfigText(['plugins' => []]));
    $service->publish($source->fresh(), agentConfigText(['plugins' => [['id' => 'p']]]));

    $manager = agentConfigsManager($source->fresh())
        ->callAction(TestAction::make('activate')->table($first))
        ->assertNotified();

    $source->refresh();
    $newest = $source->agentConfigs()->orderByDesc('version')->first();

    expect($source->agentConfigs()->count())->toBe(4)
        ->and($newest->version)->toBe(4)
        ->and($source->active_config_id)->toBe($newest->id)
        ->and($newest->document->inputs)->toEqual($first->document->inputs)
        ->and(property_exists($newest->document, 'plugins'))->toBeFalse()
        ->and($newest->document->version)->toBe(4)
        ->and($first->fresh()->version)->toBe(1);

    $manager->assertTableActionHidden('activate', $newest)
        ->assertTableActionVisible('activate', $first);
});

it('shows the errors of a stored version the validator now refuses on activation and publishes nothing', function (): void {
    $source = MachineSource::factory()->create();
    $service = resolve(MachineAgentConfigService::class);
    $service->publish($source, agentConfigText());
    $refused = MachineAgentConfig::factory()->create([
        'source_id' => $source->id,
        'version' => 1000,
        'document' => (object) ['schema' => 'other/9', 'inputs' => [], 'outputs' => []],
    ]);

    agentConfigsManager($source->fresh())
        ->callAction(TestAction::make('activate')->table($refused))
        ->assertNotified(__('mes.messages.configurationNotPublished'));

    $source->refresh();

    expect($source->agentConfigs()->count())->toBe(2)
        ->and($source->active_config_id)->not->toBe($refused->id)
        ->and($source->activeConfig->version)->toBe(1)
        ->and($source->last_config_version)->toBe(1);
});

it('offers no activate action on the active version', function (): void {
    $source = MachineSource::factory()->create();
    $active = resolve(MachineAgentConfigService::class)->publish($source, agentConfigText());

    agentConfigsManager($source->fresh())->assertTableActionHidden('activate', $active);
});

it('hides the publish and activate actions from a user who may not edit sources', function (): void {
    $source = MachineSource::factory()->create();
    $config = resolve(MachineAgentConfigService::class)->publish($source, agentConfigText());
    resolve(MachineAgentConfigService::class)->publish($source->fresh(), agentConfigText(['plugins' => []]));
    $this->actingAs(User::factory()->create());

    agentConfigsManager($source->fresh())
        ->assertActionHidden(TestAction::make('publish')->table())
        ->assertTableActionHidden('activate', $config);
});

it('lists the reports of the source read-only, newest first, with version, outcome, errors and agent version', function (): void {
    $source = MachineSource::factory()->create();
    $old = MachineAgentConfigReport::factory()->create(['source_id' => $source->id, 'config_version' => 1, 'reported_at' => '2026-10-10 08:00:00']);
    $new = MachineAgentConfigReport::factory()->create([
        'source_id' => $source->id,
        'config_version' => 2,
        'outcome' => MachineAgentConfigOutcome::Rejected,
        'errors' => ['inputs.0: unknown driver', 'outputs.0: no route'],
        'agent_version' => '0.3.1',
        'reported_at' => '2026-10-12 09:00:00',
    ]);
    $foreign = MachineAgentConfigReport::factory()->create();

    $manager = agentConfigReportsManager($source)->assertOk();

    expect($manager->instance()->getTableRecords()->modelKeys())->toBe([$new->getKey(), $old->getKey()])
        ->and($manager->instance()->getTableRecords()->modelKeys())->not->toContain($foreign->getKey());

    $manager->assertTableColumnStateSet('config_version', 2, $new)
        ->assertTableColumnStateSet('outcome', MachineAgentConfigOutcome::Rejected, $new)
        ->assertTableColumnStateSet('errors', ['inputs.0: unknown driver', 'outputs.0: no route'], $new)
        ->assertTableColumnStateSet('agent_version', '0.3.1', $new)
        ->assertTableColumnExists('reported_at');

    expect($manager->instance()->isReadOnly())->toBeTrue();
});

it('breaks a tie of report dates by the later report first', function (): void {
    $source = MachineSource::factory()->create();
    $at = '2026-10-12 09:00:00';
    $a = MachineAgentConfigReport::factory()->create(['source_id' => $source->id, 'reported_at' => $at]);
    $b = MachineAgentConfigReport::factory()->create(['source_id' => $source->id, 'reported_at' => $at]);

    $keys = agentConfigReportsManager($source)->instance()->getTableRecords()->modelKeys();

    expect($keys)->toBe([$b->getKey(), $a->getKey()]);
});

it('offers no way to write a report from the backoffice', function (): void {
    $source = MachineSource::factory()->create();
    MachineAgentConfigReport::factory()->create(['source_id' => $source->id]);

    $table = agentConfigReportsManager($source)->instance()->getTable();

    expect($table->getHeaderActions())->toBe([])
        ->and($table->getRecordActions())->toBe([])
        ->and($table->getBulkActions())->toBe([]);
});
