<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ApiTokenService;
use Modules\Core\Support\CrudApiExposure;
use Modules\Core\Support\PermissionName;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Modules\MES\Enums\MachineIncidentType;
use Modules\MES\Http\Middleware\ResolveMachineSource;
use Modules\MES\Machine\MachineAgentConfigService;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineAgentConfigReport;
use Modules\MES\Models\MachineIncident;
use Modules\MES\Models\MachineMessage;
use Modules\MES\Models\MachineSource;
use Modules\MES\Tests\Support\MesTestHelpers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CrudApiExposure::enable();
    MesTestHelpers::makeCompany();
    Queue::fake();
});

/**
 * A structurally valid `laraplate-edge-config/1` document.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agentConfigDocument(array $overrides = []): array
{
    return [
        'schema' => 'laraplate-edge-config/1',
        'inputs' => [[
            'id' => 'press-line',
            'driver' => 'opcua',
            'connection' => ['endpoint' => 'opc.tcp://10.0.5.20:4840'],
            'devices' => [['device' => 'press-07', 'points' => [['signal' => 'state', 'address' => 'ns=3;s=Machine.State', 'read' => 'subscribe']]]],
        ]],
        'outputs' => [['id' => 'laraplate-main', 'type' => 'laraplate-http', 'url' => 'https://erp.example.com', 'token' => 'secret://keystore/laraplate-main']],
        'plugins' => [['id' => 'fanuc', 'path' => '/opt/laraplate-edge/plugins/fanuc-focas', 'sha256' => 'abc']],
        ...$overrides,
    ];
}

function agentConfigService(): MachineAgentConfigService
{
    return resolve(MachineAgentConfigService::class);
}

/**
 * The three `api` permissions of the machine routes, as `permission:refresh` creates them.
 *
 * @return list<string>
 */
function agentConfigPermissionNames(): array
{
    $names = [
        PermissionName::forClass(MachineMessage::class, 'insert'),
        PermissionName::forClass(MachineAgentConfig::class, 'select'),
        PermissionName::forClass(MachineAgentConfigReport::class, 'insert'),
    ];

    foreach ($names as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'api']);
    }

    return $names;
}

/**
 * The `api` role the MES seeder gives the machine agents, with its configuration permissions and ACL.
 */
function agentConfigRole(): Role
{
    agentConfigPermissionNames();
    test()->seed(MESDatabaseSeeder::class);

    return Role::query()->where(['name' => MESDatabaseSeeder::MACHINE_AGENT_ROLE, 'guard_name' => 'api'])->sole();
}

/**
 * A source on a service account holding the machine agent role, and a token issued the way the user resource
 * issues it. `$abilities` null means every ability the account may grant.
 *
 * @param  array<string, mixed>  $attributes
 * @param  list<string>|null  $abilities
 * @return array{0: MachineSource, 1: string}
 */
function agentConfigSource(array $attributes = [], ?array $abilities = null): array
{
    $role = agentConfigRole();
    $tokens = app(ApiTokenService::class);
    $account = $tokens->tokenable(User::factory()->serviceAccount()->create());
    $account->assignRole($role);
    $source = MachineSource::factory()->create([...$attributes, 'user_id' => $account->id]);
    $account = $account->fresh();

    if ($abilities !== null) {
        return [$source, $account->createToken('machine-agent', $abilities)->plainTextToken];
    }

    return [$source, $tokens->issue($account, 'machine-agent', $tokens->grantableAbilities($account), null, null)->plainTextToken];
}

/**
 * @param  array<string, mixed>  $query
 */
function agentConfigPoll(?string $token, array $query = []): Illuminate\Testing\TestResponse
{
    $headers = $token === null ? [] : ['Authorization' => "Bearer {$token}"];

    return test()->getJson(route('mes.api.machine-agent-configs.detail', $query), $headers);
}

/**
 * @param  array<string, mixed>  $body
 */
function agentConfigReport(?string $token, array $body): Illuminate\Testing\TestResponse
{
    $headers = $token === null ? [] : ['Authorization' => "Bearer {$token}"];

    return test()->postJson(route('mes.api.machine-agent-config-reports.insert'), $body, $headers);
}

// --- Service -------------------------------------------------------------------------------------------------

it('numbers the published documents 1, 2, 3, writes the number into each and activates the last', function (): void {
    $source = MachineSource::factory()->create();
    $author = User::factory()->create();

    $first = agentConfigService()->publish($source, agentConfigDocument(), $author);
    $second = agentConfigService()->publish($source, agentConfigDocument(['version' => 99]));
    $third = agentConfigService()->publish($source, agentConfigDocument());

    expect([$first->version, $second->version, $third->version])->toBe([1, 2, 3])
        ->and([$first->fresh()->document['version'], $second->fresh()->document['version'], $third->fresh()->document['version']])->toBe([1, 2, 3])
        ->and($first->created_by)->toBe($author->id)
        ->and($second->created_by)->toBeNull()
        ->and($first->company_id)->toBe($source->company_id)
        ->and($source->fresh()->active_config_id)->toBe($third->id)
        ->and($source->fresh()->activeConfig?->is($third))->toBeTrue()
        ->and($source->agentConfigs()->count())->toBe(3);
});

it('numbers the versions of each source on their own', function (): void {
    $first_source = MachineSource::factory()->create();
    $second_source = MachineSource::factory()->create();

    agentConfigService()->publish($first_source, agentConfigDocument());
    agentConfigService()->publish($first_source, agentConfigDocument());

    expect(agentConfigService()->publish($second_source, agentConfigDocument())->version)->toBe(1);
});

it('refuses a document that is not structurally a laraplate-edge-config/1 document and stores nothing', function (array $document, string $expected_error): void {
    $source = MachineSource::factory()->create();
    $active = agentConfigService()->publish($source, agentConfigDocument());

    $errors = agentConfigService()->validate($document);

    expect($errors)->not->toBeEmpty()
        ->and(implode("\n", $errors))->toContain($expected_error);

    try {
        agentConfigService()->publish($source, $document);
        $this->fail('The document was published.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('document')
            ->and($exception->errors()['document'])->toBe($errors);
    }

    expect(MachineAgentConfig::query()->count())->toBe(1)
        ->and($source->fresh()->active_config_id)->toBe($active->id);
})->with([
    'another schema' => [agentConfigDocument(['schema' => 'laraplate-edge-config/2']), 'schema'],
    'no schema' => [array_diff_key(agentConfigDocument(), ['schema' => true]), 'schema'],
    'inputs not a list' => [agentConfigDocument(['inputs' => ['press-line' => ['id' => 'press-line']]]), 'inputs'],
    'inputs a string' => [agentConfigDocument(['inputs' => 'press-line']), 'inputs'],
    'an input without id' => [agentConfigDocument(['inputs' => [['driver' => 'opcua']]]), 'inputs.0.id'],
    'an input with a numeric id' => [agentConfigDocument(['inputs' => [['id' => 7]]]), 'inputs.0.id'],
    'an input that is not an object' => [agentConfigDocument(['inputs' => ['press-line']]), 'inputs.0'],
    'an output without id' => [agentConfigDocument(['outputs' => [['type' => 'laraplate-http']]]), 'outputs.0.id'],
    'a plugin with an empty id' => [agentConfigDocument(['plugins' => [['id' => '']]]), 'plugins.0.id'],
    'no outputs' => [array_diff_key(agentConfigDocument(), ['outputs' => true]), 'outputs'],
    'an unknown top-level key' => [agentConfigDocument(['metrics' => []]), 'metrics'],
    'a version that is not an integer' => [agentConfigDocument(['version' => '3']), 'version'],
]);

it('finds no error in a valid document, with or without plugins and version', function (): void {
    expect(agentConfigService()->validate(agentConfigDocument()))->toBe([])
        ->and(agentConfigService()->validate(array_diff_key(agentConfigDocument(), ['plugins' => true])))->toBe([])
        ->and(agentConfigService()->validate(agentConfigDocument(['version' => 12])))->toBe([])
        ->and(agentConfigService()->validate(agentConfigDocument(['inputs' => [], 'outputs' => []])))->toBe([]);
});

it('keeps the structural schema it validates against in the module', function (): void {
    $schema = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/resources/edge/laraplate-edge-config-1.schema.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($schema['properties']['schema']['const'])->toBe(MachineAgentConfigService::SCHEMA)
        ->and($schema['required'])->toBe(['schema', 'inputs', 'outputs'])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and(array_keys($schema['properties']))->toBe(['schema', 'version', 'inputs', 'outputs', 'plugins'])
        ->and($schema['properties']['version'])->toMatchArray(['type' => 'integer', 'minimum' => 1])
        ->and($schema['$defs']['components']['type'])->toBe('array')
        ->and($schema['$defs']['component']['required'])->toBe(['id'])
        ->and($schema['$defs']['component']['properties']['id'])->toBe(['type' => 'string', 'minLength' => 1]);
});

it('activates an older version by publishing its document again as the next version', function (): void {
    $source = MachineSource::factory()->create();
    $author = User::factory()->create();
    $first = agentConfigService()->publish($source, agentConfigDocument(['inputs' => [['id' => 'old-line']]]));
    agentConfigService()->publish($source, agentConfigDocument());
    agentConfigService()->publish($source, agentConfigDocument());

    $activated = agentConfigService()->activate($first, $author);

    expect($activated->version)->toBe(4)
        ->and($activated->is($first))->toBeFalse()
        ->and($activated->created_by)->toBe($author->id)
        ->and($activated->document)->toBe([...$first->document, 'version' => 4])
        ->and($activated->document['inputs'])->toBe([['id' => 'old-line']])
        ->and($source->fresh()->active_config_id)->toBe($activated->id)
        ->and($first->fresh()->version)->toBe(1)
        ->and($first->fresh()->document['version'])->toBe(1)
        ->and(MachineAgentConfig::query()->count())->toBe(4);
});

it('leaves the active version as it is when it is activated again', function (): void {
    $source = MachineSource::factory()->create();
    $active = agentConfigService()->publish($source, agentConfigDocument());

    expect(agentConfigService()->activate($active)->is($active))->toBeTrue()
        ->and(MachineAgentConfig::query()->count())->toBe(1);
});

it('lets a source with an active configuration be deleted for good', function (): void {
    $source = MachineSource::factory()->create();
    agentConfigService()->publish($source, agentConfigDocument());

    $source->forceDelete();

    expect(MachineSource::query()->withoutGlobalScopes()->withTrashed()->count())->toBe(0)
        ->and(MachineAgentConfig::query()->count())->toBe(0);
});

// --- Configuration detail ------------------------------------------------------------------------------------

it('serves the active configuration of the caller source with its version', function (): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());
    $active = agentConfigService()->publish($source, agentConfigDocument());
    agentConfigService()->publish(MachineSource::factory()->create(), agentConfigDocument());

    agentConfigPoll($token)->assertOk()->assertExactJson(['version' => 2, 'document' => $active->fresh()->document]);
});

it('answers 304 with no body when the agent already holds the active version', function (): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());

    $response = agentConfigPoll($token, ['version' => 1]);

    $response->assertStatus(304);
    expect($response->getContent())->toBe('');
});

it('serves the active configuration when the agent holds an older version', function (): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());
    agentConfigService()->publish($source, agentConfigDocument());

    agentConfigPoll($token, ['version' => 1])->assertOk()->assertJsonPath('version', 2);
});

it('serves an activated older document under a higher version to an agent polling with the current one', function (): void {
    [$source, $token] = agentConfigSource();
    $first = agentConfigService()->publish($source, agentConfigDocument(['inputs' => [['id' => 'old-line']]]));
    agentConfigService()->publish($source, agentConfigDocument());
    agentConfigService()->publish($source, agentConfigDocument());
    agentConfigPoll($token, ['version' => 3])->assertStatus(304);

    agentConfigService()->activate($first);

    agentConfigPoll($token, ['version' => 3])->assertOk()
        ->assertJsonPath('version', 4)
        ->assertJsonPath('document.version', 4)
        ->assertJsonPath('document.inputs', [['id' => 'old-line']]);
});

it('answers 404 when the source has no active configuration', function (): void {
    [, $token] = agentConfigSource();
    agentConfigService()->publish(MachineSource::factory()->create(), agentConfigDocument());

    agentConfigPoll($token)->assertNotFound();
    agentConfigPoll($token, ['version' => 1])->assertNotFound();
});

it('refuses a version that is not a positive integer with 422', function (string $version): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());

    agentConfigPoll($token, ['version' => $version])->assertStatus(422)->assertJsonValidationErrors('version');
})->with([
    'a word' => ['latest'],
    'a decimal' => ['1.5'],
    'zero' => ['0'],
    'a negative number' => ['-1'],
    'empty' => [''],
]);

// --- Reports -------------------------------------------------------------------------------------------------

it('records an applied report with 201 and opens no incident', function (): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());

    agentConfigReport($token, ['version' => 1, 'outcome' => 'applied', 'errors' => null, 'agent_version' => '0.3.1'])->assertCreated();

    $report = MachineAgentConfigReport::query()->sole();

    expect($report->source_id)->toBe($source->id)
        ->and($report->company_id)->toBe($source->company_id)
        ->and($report->config_version)->toBe(1)
        ->and($report->outcome)->toBe(MachineAgentConfigOutcome::Applied)
        ->and($report->errors)->toBeNull()
        ->and($report->agent_version)->toBe('0.3.1')
        ->and($report->reported_at)->not->toBeNull()
        ->and($source->agentConfigReports()->count())->toBe(1)
        ->and(MachineIncident::query()->count())->toBe(0);
});

it('opens one config_rejected incident for a failed apply', function (string $outcome): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());
    $errors = ['inputs.0: driver opcua did not start within 30s'];

    agentConfigReport($token, ['version' => 1, 'outcome' => $outcome, 'errors' => $errors, 'agent_version' => '0.3.1'])->assertCreated();

    $incident = MachineIncident::query()->sole();

    expect(MachineAgentConfigReport::query()->count())->toBe(1)
        ->and($incident->source_id)->toBe($source->id)
        ->and($incident->type)->toBe(MachineIncidentType::ConfigRejected)
        ->and($incident->detail)->toBe(['version' => 1, 'outcome' => $outcome, 'errors' => $errors]);
})->with(['rejected', 'rolled_back']);

it('refuses an invalid report with 422 and records neither the report nor an incident', function (array $body, string $field): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());
    $other = MachineSource::factory()->create();
    agentConfigService()->publish($other, agentConfigDocument());
    agentConfigService()->publish($other, agentConfigDocument());

    agentConfigReport($token, [...['version' => 1, 'outcome' => 'rejected', 'errors' => ['boom'], 'agent_version' => '0.3.1'], ...$body])
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(MachineAgentConfigReport::query()->count())->toBe(0)
        ->and(MachineIncident::query()->count())->toBe(0);
})->with([
    'an unknown outcome' => [['outcome' => 'exploded'], 'outcome'],
    'no agent version' => [['agent_version' => null], 'agent_version'],
    'an agent version too long' => [['agent_version' => str_repeat('a', 65)], 'agent_version'],
    'a version never published' => [['version' => 9], 'version'],
    'a version of another source only' => [['version' => 2], 'version'],
    'a version as a string' => [['version' => '1'], 'version'],
    'no version' => [['version' => null], 'version'],
    'errors as an object map' => [['errors' => ['inputs' => 'boom']], 'errors'],
    'an error that is not a string' => [['errors' => [['boom']]], 'errors.0'],
    'too many errors' => [['errors' => array_fill(0, 101, 'boom')], 'errors'],
    'an error too long' => [['errors' => [str_repeat('a', 1001)]], 'errors.0'],
]);

// --- Authorization -------------------------------------------------------------------------------------------

it('refuses a missing or unknown token with 401 on both configuration routes', function (?string $token): void {
    [$source] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());

    agentConfigPoll($token)->assertUnauthorized();
    agentConfigReport($token, ['version' => 1, 'outcome' => 'rejected', 'errors' => [], 'agent_version' => '1'])->assertUnauthorized();

    expect(MachineAgentConfigReport::query()->count())->toBe(0);
})->with([
    'no token' => [null],
    'unknown token' => ['999|nonsense'],
]);

it('refuses a token without the route ability with 403', function (): void {
    [$source, $token] = agentConfigSource(abilities: [PermissionName::forClass(MachineMessage::class, 'insert')]);
    agentConfigService()->publish($source, agentConfigDocument());

    agentConfigPoll($token)->assertForbidden();
    agentConfigReport($token, ['version' => 1, 'outcome' => 'rejected', 'errors' => [], 'agent_version' => '1'])->assertForbidden();

    expect(MachineAgentConfigReport::query()->count())->toBe(0)
        ->and(MachineIncident::query()->where('type', MachineIncidentType::ConfigRejected->value)->count())->toBe(0);
});

it('refuses the agent with 403 and one authentication failure once its role loses the configuration permissions', function (): void {
    [$source, $token] = agentConfigSource();
    agentConfigService()->publish($source, agentConfigDocument());
    $role = agentConfigRole();
    $role->revokePermissionTo(PermissionName::forClass(MachineAgentConfig::class, 'select'));
    $role->revokePermissionTo(PermissionName::forClass(MachineAgentConfigReport::class, 'insert'));

    agentConfigPoll($token)->assertForbidden();
    agentConfigReport($token, ['version' => 1, 'outcome' => 'rejected', 'errors' => [], 'agent_version' => '1'])->assertForbidden();

    $incidents = MachineIncident::query()->where('source_id', $source->id)->get();

    expect(MachineAgentConfigReport::query()->count())->toBe(0)
        ->and($incidents)->toHaveCount(1)
        ->and($incidents->sole()->type)->toBe(MachineIncidentType::AuthFailure)
        ->and($incidents->sole()->detail)->toBe(['reason' => ResolveMachineSource::REASON_MISSING_PERMISSION]);
});

it('refuses the token of a regular user holding the configuration permissions with 403', function (): void {
    $regular = app(ApiTokenService::class)->tokenable(User::factory()->create());
    $regular->assignRole(agentConfigRole());
    $source = MachineSource::factory()->create();
    MachineSource::query()->withoutGlobalScopes()->toBase()->update(['user_id' => $regular->id]);
    agentConfigService()->publish($source, agentConfigDocument());
    $token = $regular->fresh()->createToken('t', agentConfigPermissionNames())->plainTextToken;

    agentConfigPoll($token)->assertForbidden();
    agentConfigReport($token, ['version' => 1, 'outcome' => 'rejected', 'errors' => [], 'agent_version' => '1'])->assertForbidden();

    expect(MachineAgentConfigReport::query()->count())->toBe(0)
        ->and(MachineIncident::query()->count())->toBe(0);
});

it('refuses an inactive source with 403 and one authentication failure', function (): void {
    [$source, $token] = agentConfigSource(['is_active' => false]);
    agentConfigService()->publish($source, agentConfigDocument());

    agentConfigPoll($token)->assertForbidden();
    agentConfigReport($token, ['version' => 1, 'outcome' => 'rejected', 'errors' => [], 'agent_version' => '1'])->assertForbidden();

    $incidents = MachineIncident::query()->where('source_id', $source->id)->get();

    expect(MachineAgentConfigReport::query()->count())->toBe(0)
        ->and($incidents)->toHaveCount(1)
        ->and($incidents->sole()->detail)->toBe(['reason' => ResolveMachineSource::REASON_INACTIVE_SOURCE]);
});

it('refuses a service account that speaks for no source with 403', function (): void {
    $tokens = app(ApiTokenService::class);
    $account = $tokens->tokenable(User::factory()->serviceAccount()->create());
    $account->assignRole(agentConfigRole());
    $token = $tokens->issue($account->fresh(), 'machine-agent', agentConfigPermissionNames(), null, null)->plainTextToken;

    agentConfigPoll($token)->assertForbidden();
    agentConfigReport($token, ['version' => 1, 'outcome' => 'rejected', 'errors' => [], 'agent_version' => '1'])->assertForbidden();
});

// --- Generic CRUD --------------------------------------------------------------------------------------------

it('shows the agent only the configurations of its own source through the generic CRUD reads', function (): void {
    [$source, $token] = agentConfigSource();
    $own = agentConfigService()->publish($source, agentConfigDocument());
    $foreign = agentConfigService()->publish(MachineSource::factory()->create(), agentConfigDocument());
    $headers = ['Authorization' => "Bearer {$token}"];

    $list = test()->getJson('/api/v1/select/mes/machine-agent-configs', $headers)->assertOk();

    expect(collect($list->json('data'))->pluck('id')->all())->toBe([$own->id]);

    test()->getJson('/api/v1/detail/MES/machine-agent-configs?' . http_build_query(['id' => $own->id]), $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $own->id);
    $foreign_detail = test()->getJson('/api/v1/detail/MES/machine-agent-configs?' . http_build_query(['id' => $foreign->id]), $headers);

    expect($foreign_detail->status())->toBeIn([403, 404])
        ->and((string) $foreign_detail->getContent())->not->toContain('"id":' . $foreign->id);
});

it('refuses the generic CRUD writes on configurations and reports, even to a role widened to them', function (string $method, string $uri, Closure $body): void {
    [$source, $token] = agentConfigSource();
    $config = agentConfigService()->publish($source, agentConfigDocument());
    $report = MachineAgentConfigReport::factory()->create(['source_id' => $source->id, 'config_version' => 1]);
    $role = agentConfigRole();

    foreach ([MachineAgentConfig::class, MachineAgentConfigReport::class] as $model) {
        foreach (['insert', 'update', 'delete'] as $operation) {
            $role->givePermissionTo(Permission::query()->firstOrCreate(['name' => PermissionName::forClass($model, $operation), 'guard_name' => 'api']));
        }
    }

    $abilities = [];

    foreach ([MachineAgentConfig::class, MachineAgentConfigReport::class] as $model) {
        foreach (['select', 'insert', 'update', 'delete'] as $operation) {
            $abilities[] = PermissionName::forClass($model, $operation);
        }
    }

    $widened = app(ApiTokenService::class)->tokenable($source->serviceAccount)->createToken('widened', $abilities)->plainTextToken;

    test()->json($method, $uri, $body($source, $config, $report), ['Authorization' => "Bearer {$widened}"])->assertForbidden();

    expect(MachineAgentConfig::query()->count())->toBe(1)
        ->and($config->fresh()->document)->toBe($config->document)
        ->and(MachineAgentConfigReport::query()->count())->toBe(1)
        ->and($report->fresh()->outcome)->toBe(MachineAgentConfigOutcome::Applied);
})->with([
    'insert a configuration' => ['POST', '/api/v1/insert/mes/machine-agent-configs', fn (MachineSource $source): array => ['company_id' => $source->company_id, 'source_id' => $source->id, 'version' => 2, 'document' => ['schema' => 'x']]],
    'update a configuration' => ['PATCH', '/api/v1/update/mes/machine-agent-configs', fn (MachineSource $source, MachineAgentConfig $config): array => ['id' => $config->id, 'document' => ['schema' => 'x']]],
    'delete a configuration' => ['DELETE', '/api/v1/delete/mes/machine-agent-configs', fn (MachineSource $source, MachineAgentConfig $config): array => ['id' => $config->id]],
    'insert a report on the generic path' => ['POST', '/api/v1/insert/MES/machine-agent-config-reports', fn (MachineSource $source): array => ['company_id' => $source->company_id, 'source_id' => $source->id, 'config_version' => 1, 'outcome' => 'rejected', 'agent_version' => '1', 'reported_at' => now()->toIso8601String()]],
    'update a report' => ['PATCH', '/api/v1/update/mes/machine-agent-config-reports', fn (MachineSource $source, MachineAgentConfig $config, MachineAgentConfigReport $report): array => ['id' => $report->id, 'outcome' => 'rejected']],
    'delete a report' => ['DELETE', '/api/v1/delete/mes/machine-agent-config-reports', fn (MachineSource $source, MachineAgentConfig $config, MachineAgentConfigReport $report): array => ['id' => $report->id]],
]);
