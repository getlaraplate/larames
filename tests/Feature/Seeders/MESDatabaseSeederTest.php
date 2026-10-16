<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\RelationFilter;
use Modules\Core\Database\Seeders\PermissionRefreshSeeder;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedGraphBuilder;
use Modules\Core\Seeding\SeedNode;
use Modules\Core\Support\PermissionName;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineAgentConfigReport;
use Modules\MES\Models\MachineMessage;

uses(RefreshDatabase::class);

it('seeds MES runtime settings stamped with the MES module', function (): void {
    $this->seed(MESDatabaseSeeder::class);

    $names = collect(MESDatabaseSeeder::runtimeSettingDefinitions())->pluck('name');

    $settings = Setting::query()->withoutGlobalScopes()->whereIn('name', $names)->get();

    expect($settings)->toHaveCount($names->count())
        ->and($settings->pluck('module')->unique()->all())->toBe(['MES']);
});

it('is idempotent and leaves an operator-changed value untouched on a second run', function (): void {
    $this->seed(MESDatabaseSeeder::class);

    Setting::query()->withoutGlobalScopes()
        ->where('name', 'lots.number_format')
        ->update(['value' => json_encode('{SEQ}'), 'description' => 'drifted']);

    $this->seed(MESDatabaseSeeder::class);

    $setting = Setting::query()->withoutGlobalScopes()
        ->where('name', 'lots.number_format')->sole();

    expect($setting->value)->toBe('{SEQ}')
        ->and($setting->description)->toBe('Lot number generation format');
});

it('seeds the machine agent role on the api guard with the three machine route permissions', function (): void {
    Artisan::call('permission:refresh', ['--quiet' => true]);

    $this->seed(MESDatabaseSeeder::class);
    $this->seed(MESDatabaseSeeder::class);

    $roles = Role::query()->where('name', MESDatabaseSeeder::MACHINE_AGENT_ROLE)->get();

    expect(MESDatabaseSeeder::MACHINE_AGENT_ROLE)->toBe('mes-machine-agent')
        ->and($roles)->toHaveCount(1)
        ->and($roles->sole()->guard_name)->toBe('api')
        ->and($roles->sole()->permissions()->pluck('name')->sort()->values()->all())->toBe(collect([
            PermissionName::forClass(MachineMessage::class, 'insert'),
            PermissionName::forClass(MachineAgentConfig::class, 'select'),
            PermissionName::forClass(MachineAgentConfigReport::class, 'insert'),
        ])->sort()->values()->all())
        ->and($roles->sole()->permissions()->pluck('guard_name')->unique()->all())->toBe(['api']);
});

it('narrows the machine agent role to the configurations of its own source', function (): void {
    Artisan::call('permission:refresh', ['--quiet' => true]);

    $this->seed(MESDatabaseSeeder::class);

    $role = Role::query()->where(['name' => MESDatabaseSeeder::MACHINE_AGENT_ROLE, 'guard_name' => 'api'])->sole();
    $permission = Permission::query()->where(['name' => PermissionName::forClass(MachineAgentConfig::class, 'select'), 'guard_name' => 'api'])->sole();
    $acl = ACL::query()->where(['role_id' => $role->id, 'permission_id' => $permission->id])->sole();

    expect($acl->is_active)->toBeTrue()
        ->and($acl->unrestricted)->toBeFalse()
        ->and($acl->filters->toArray())->toBe((new FiltersGroup([
            new RelationFilter('source', new FiltersGroup([new Filter('user_id', '@user.id', FilterOperator::Equals)])),
        ]))->toArray());
});

it('keeps an ACL of the machine agent role an operator changed on a second run', function (): void {
    Artisan::call('permission:refresh', ['--quiet' => true]);
    $this->seed(MESDatabaseSeeder::class);
    ACL::query()->update(['description' => 'changed by an operator']);

    $this->seed(MESDatabaseSeeder::class);

    expect(ACL::query()->count())->toBe(1)
        ->and(ACL::query()->sole()->description)->toBe('changed by an operator');
});

it('runs after the permission refresh in the seed graph, so the machine agent role finds its permissions', function (): void {
    $order = array_map(static fn (SeedNode $node): string => $node->seederClass, app(SeedGraphBuilder::class)->build());
    $positions = array_flip($order);

    expect(MESDatabaseSeeder::dependsOn())->toContain(PermissionRefreshSeeder::class)
        ->and($positions)->toHaveKeys([PermissionRefreshSeeder::class, MESDatabaseSeeder::class])
        ->and($positions[PermissionRefreshSeeder::class])->toBeLessThan($positions[MESDatabaseSeeder::class]);
});
