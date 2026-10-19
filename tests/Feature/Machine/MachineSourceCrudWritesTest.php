<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ApiTokenService;
use Modules\Core\Support\CrudApiExposure;
use Modules\Core\Support\PermissionName;
use Modules\MES\Models\MachineSource;
use Modules\MES\Tests\Support\MesTestHelpers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CrudApiExposure::enable();
    MesTestHelpers::makeCompany();
});

/**
 * A caller of the API whose role may insert, update and delete machine sources, with a token carrying every ability
 * it may grant: only the model decides whether the generic CRUD writes are allowed.
 */
function sourceWriterToken(): string
{
    $role = Role::query()->firstOrCreate(['name' => 'source-writer', 'guard_name' => 'api']);

    foreach (['select', 'insert', 'update', 'delete'] as $operation) {
        $role->givePermissionTo(Permission::query()->firstOrCreate(['name' => PermissionName::forClass(MachineSource::class, $operation), 'guard_name' => 'api']));
    }

    $tokens = app(ApiTokenService::class);
    $caller = $tokens->tokenable(User::factory()->serviceAccount()->create());
    $caller->assignRole($role);
    $caller = $caller->fresh();

    return $tokens->issue($caller, 'writer', $tokens->grantableAbilities($caller), null, null)->plainTextToken;
}

it('refuses to link a service account to a source, or to change or delete a source, through the generic CRUD', function (string $method, string $uri, Closure $body): void {
    $token = sourceWriterToken();
    $victim = User::factory()->serviceAccount()->create();
    $source = MachineSource::factory()->create(['user_id' => null]);

    test()->json($method, $uri, $body($source, $victim), ['Authorization' => "Bearer {$token}"])->assertForbidden();

    expect($source->fresh()->user_id)->toBeNull()
        ->and(MachineSource::query()->count())->toBe(1);
})->with([
    'link an account' => ['PATCH', '/api/v1/update/mes/machine-sources', fn (MachineSource $source, User $victim): array => ['id' => $source->id, 'user_id' => $victim->id]],
    'insert a source' => ['POST', '/api/v1/insert/mes/machine-sources', fn (MachineSource $source, User $victim): array => ['company_id' => $source->company_id, 'code' => 'rogue', 'name' => 'Rogue', 'normalizer' => 'canonical', 'transport' => 'http', 'user_id' => $victim->id]],
    'delete a source' => ['DELETE', '/api/v1/delete/mes/machine-sources', fn (MachineSource $source): array => ['id' => $source->id]],
]);

it('still lets the backoffice and the services change a source through the model', function (): void {
    $account = User::factory()->serviceAccount()->create();
    $source = MachineSource::factory()->create(['user_id' => null]);

    $source->update(['user_id' => $account->id]);

    expect($source->fresh()->user_id)->toBe($account->id);
});
