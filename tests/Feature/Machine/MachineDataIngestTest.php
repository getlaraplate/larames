<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ApiTokenService;
use Modules\Core\Support\CrudApiExposure;
use Modules\Core\Support\PermissionName;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;
use Modules\MES\Enums\MachineIncidentType;
use Modules\MES\Jobs\ProcessMachineMessageJob;
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

function ingestEnvelope(string $message_id = 'm-1', int $samples = 1): array
{
    return [
        'protocol' => 'laraplate-machine/1',
        'message_id' => $message_id,
        'source_seq' => 1,
        'sent_at' => now()->toIso8601ZuluString('millisecond'),
        'devices' => [[
            'device' => 'd1',
            'type' => 'data',
            'samples' => array_map(static fn (int $i): array => ['signal' => "s{$i}", 'ts' => now()->toIso8601ZuluString('millisecond'), 'value' => $i], range(1, $samples)),
        ]],
    ];
}

function ingest(array $envelope, ?string $token): Illuminate\Testing\TestResponse
{
    $headers = $token === null ? [] : ['Authorization' => "Bearer {$token}"];

    return test()->postJson(route('mes.api.machine-messages.insert'), $envelope, $headers);
}

/**
 * The ingest permission of the `api` guard, as `permission:refresh` creates it.
 */
function machineIngestPermission(): Permission
{
    return Permission::query()->firstOrCreate(['name' => PermissionName::forClass(MachineMessage::class, 'insert'), 'guard_name' => 'api']);
}

/**
 * The `api` role the MES seeder gives the machine agents.
 */
function machineAgentRole(): Role
{
    machineIngestPermission();
    test()->seed(MESDatabaseSeeder::class);

    return Role::query()->where(['name' => MESDatabaseSeeder::MACHINE_AGENT_ROLE, 'guard_name' => 'api'])->sole();
}

/**
 * A source linked to a service account holding the machine agent role, and a token issued for that account
 * the way the user resource issues it. `$abilities` null means every ability the account may grant.
 *
 * @param  list<string>|null  $abilities
 * @param  list<string>|null  $allowed_cidrs
 * @return array{0: MachineSource, 1: string}
 */
function sourceWithToken(array $attributes = [], ?array $abilities = null, ?array $allowed_cidrs = null): array
{
    $role = machineAgentRole();
    $tokens = app(ApiTokenService::class);
    $account = $tokens->tokenable(User::factory()->serviceAccount()->create());
    $account->assignRole($role);
    $source = MachineSource::factory()->create([...$attributes, 'user_id' => $account->id]);
    $account = $account->fresh();

    if ($abilities !== null) {
        return [$source, $account->createToken('machine-ingest', $abilities)->plainTextToken];
    }

    return [$source, $tokens->issue($account, 'machine-ingest', $tokens->grantableAbilities($account), null, $allowed_cidrs)->plainTextToken];
}

it('accepts a valid envelope with 202, stores it and queues the job', function (): void {
    [$source, $token] = sourceWithToken();

    ingest(ingestEnvelope(), $token)->assertStatus(202)->assertJson(['status' => 'accepted', 'message_id' => 'm-1']);

    expect(MachineMessage::query()->where('source_id', $source->id)->count())->toBe(1);
    Queue::assertPushed(ProcessMachineMessageJob::class, 1);
});

it('answers a resend with 200 and duplicate true', function (): void {
    [, $token] = sourceWithToken();
    ingest(ingestEnvelope(), $token)->assertStatus(202);

    ingest(ingestEnvelope(), $token)->assertOk()->assertJson(['status' => 'duplicate', 'duplicate' => true, 'message_id' => 'm-1']);

    expect(MachineMessage::query()->count())->toBe(1);
    Queue::assertPushed(ProcessMachineMessageJob::class, 1);
});

it('refuses a missing or unknown token with 401', function (?string $token): void {
    sourceWithToken();

    ingest(ingestEnvelope(), $token)->assertUnauthorized();

    expect(MachineMessage::query()->count())->toBe(0);
})->with([
    'no token' => [null],
    'unknown token' => ['999|nonsense'],
]);

it('refuses an expired token with 401', function (): void {
    [$source] = sourceWithToken();
    $account = app(ApiTokenService::class)->tokenable($source->serviceAccount);
    $expired = $account->createToken('old', [PermissionName::forClass(MachineMessage::class, 'insert')], now()->subMinute())->plainTextToken;

    ingest(ingestEnvelope(), $expired)->assertUnauthorized();
});

it('refuses a token without the ingest ability with 403', function (): void {
    [, $token] = sourceWithToken(abilities: [PermissionName::forClass(MachineMessage::class, 'select')]);

    ingest(ingestEnvelope(), $token)->assertForbidden();

    expect(MachineMessage::query()->count())->toBe(0);
});

it('refuses a token used from outside its allowed networks with 403', function (): void {
    [, $token] = sourceWithToken(allowed_cidrs: ['10.0.0.0/8']);

    ingest(ingestEnvelope(), $token)->assertForbidden();

    expect(MachineMessage::query()->count())->toBe(0);
});

it('refuses the token once the role no longer holds the ingest permission with 403', function (): void {
    [, $token] = sourceWithToken();
    machineAgentRole()->revokePermissionTo(machineIngestPermission());

    ingest(ingestEnvelope(), $token)->assertForbidden();

    expect(MachineMessage::query()->count())->toBe(0);
});

it('refuses the token of a regular user holding the ingest permission with 403 and stores nothing', function (): void {
    $regular = app(ApiTokenService::class)->tokenable(User::factory()->create());
    $regular->assignRole(machineAgentRole());
    MachineSource::factory()->create();
    MachineSource::query()->withoutGlobalScopes()->toBase()->update(['user_id' => $regular->id]);
    $token = $regular->fresh()->createToken('t', [PermissionName::forClass(MachineMessage::class, 'insert')])->plainTextToken;

    ingest(ingestEnvelope(), $token)->assertForbidden();

    expect(MachineMessage::query()->count())->toBe(0)
        ->and(MachineIncident::query()->count())->toBe(0);
});

it('refuses a service account that speaks for no source with 403', function (): void {
    $role = machineAgentRole();
    $tokens = app(ApiTokenService::class);
    $account = $tokens->tokenable(User::factory()->serviceAccount()->create());
    $account->assignRole($role);
    $token = $tokens->issue($account->fresh(), 'machine-ingest', [PermissionName::forClass(MachineMessage::class, 'insert')], null, null)->plainTextToken;

    ingest(ingestEnvelope(), $token)->assertForbidden();

    expect(MachineMessage::query()->count())->toBe(0);
});

it('refuses an inactive source with 403 and records one authentication failure for two refused calls', function (): void {
    [$source, $token] = sourceWithToken(['is_active' => false]);

    ingest(ingestEnvelope('a'), $token)->assertForbidden();
    ingest(ingestEnvelope('b'), $token)->assertForbidden();

    $incidents = MachineIncident::query()->where('source_id', $source->id)->get();

    expect(MachineMessage::query()->count())->toBe(0)
        ->and($incidents)->toHaveCount(1)
        ->and($incidents->sole()->type)->toBe(MachineIncidentType::AuthFailure)
        ->and($incidents->sole()->detail)->toBe(['reason' => 'inactive_source']);
});

it('refuses a soft-deleted source with 403 and records at most one authentication failure', function (): void {
    [$source, $token] = sourceWithToken();
    $source->delete();

    ingest(ingestEnvelope('a'), $token)->assertForbidden();
    ingest(ingestEnvelope('b'), $token)->assertForbidden();

    expect(MachineMessage::query()->count())->toBe(0)
        ->and(MachineIncident::query()->where('source_id', $source->id)->count())->toBeLessThanOrEqual(1);
});

it('refuses a source that is not an http canonical source with 403', function (array $attributes): void {
    [, $token] = sourceWithToken($attributes);

    ingest(ingestEnvelope(), $token)->assertForbidden();
})->with([
    'mqtt transport' => [['transport' => 'mqtt']],
    'mapped_json normaliser' => [['normalizer' => 'mapped_json']],
]);

it('refuses an invalid envelope with 422 and the schema errors', function (): void {
    [, $token] = sourceWithToken();
    $envelope = ingestEnvelope();
    $envelope['protocol'] = 'laraplate-machine/9';

    ingest($envelope, $token)->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['protocol']]);
    expect(MachineMessage::query()->count())->toBe(0);
});

it('refuses a body that is not JSON with 422', function (): void {
    [, $token] = sourceWithToken();

    test()->call('POST', route('mes.api.machine-messages.insert'), [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token}", 'CONTENT_TYPE' => 'application/json'], '{broken')->assertStatus(422);
});

it('refuses a body above the size limit and a message above the sample limit with 413', function (): void {
    [, $token] = sourceWithToken();

    config(['mes.machine.max_body_kb' => 1]);
    ingest(ingestEnvelope('big', 60), $token)->assertStatus(413);

    config(['mes.machine.max_body_kb' => 1024, 'mes.machine.max_samples' => 2]);
    ingest(ingestEnvelope('many', 3), $token)->assertStatus(413);

    expect(MachineMessage::query()->count())->toBe(0);
});

it('limits requests per service account and says when to retry', function (): void {
    config(['mes.machine.rate_limit_per_minute' => 2]);
    [$source, $token] = sourceWithToken();
    $tokens = app(ApiTokenService::class);
    $account = $tokens->tokenable($source->serviceAccount);
    $second_token = $tokens->issue($account, 'second', $tokens->grantableAbilities($account), null, null)->plainTextToken;
    // The limiter counts in Redis, which outlives the test database: an earlier test may have spent this id's budget.
    Redis::connection()->del(md5('mes-machine-ingest' . 'user:' . $account->id));

    ingest(ingestEnvelope('a'), $token)->assertStatus(202);
    ingest(ingestEnvelope('b'), $second_token)->assertStatus(202);

    ingest(ingestEnvelope('c'), $token)->assertStatus(429)->assertHeader('Retry-After');
    ingest(ingestEnvelope('d'), $second_token)->assertStatus(429);
});

it('keeps tokens and payloads out of the logs', function (): void {
    $log = Log::spy();
    [, $token] = sourceWithToken(['is_active' => false]);

    ingest(ingestEnvelope('secret-payload'), $token)->assertForbidden();
    ingest(ingestEnvelope('secret-payload'), '999|nonsense')->assertUnauthorized();

    $log->shouldHaveReceived('warning')->atLeast()->once()->withArgs(static fn (string $message, array $context = []): bool => ! str_contains($message . json_encode($context), $token)
        && ! str_contains($message . json_encode($context), 'nonsense')
        && ! str_contains($message . json_encode($context), 'secret-payload'));
});

it('stops accepting a token revoked on its service account', function (): void {
    [$source, $token] = sourceWithToken();
    ingest(ingestEnvelope('a'), $token)->assertStatus(202);

    app(ApiTokenService::class)->tokenable($source->serviceAccount)->tokens()->delete();

    ingest(ingestEnvelope('b'), $token)->assertUnauthorized();
});

it('limits failed authentications per address through the API authentication', function (): void {
    config(['core.api.rate_limit_per_minute' => 3]);

    for ($i = 0; $i < 3; $i++) {
        ingest(ingestEnvelope(), "999|random-{$i}")->assertUnauthorized();
    }

    ingest(ingestEnvelope(), '999|random-last')->assertStatus(429)->assertHeader('Retry-After');
});

it('refuses too many samples before validating the envelope', function (): void {
    [, $token] = sourceWithToken();
    config(['mes.machine.max_samples' => 2]);
    $envelope = ingestEnvelope('big', 3);
    $envelope['protocol'] = 'laraplate-machine/9';

    ingest($envelope, $token)->assertStatus(413);
});
