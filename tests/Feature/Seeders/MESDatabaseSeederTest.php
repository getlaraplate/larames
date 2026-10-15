<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Database\Seeders\PermissionRefreshSeeder;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedGraphBuilder;
use Modules\Core\Seeding\SeedNode;
use Modules\Core\Support\PermissionName;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;
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

it('seeds the machine agent role on the api guard with the ingest permission only', function (): void {
    Artisan::call('permission:refresh', ['--quiet' => true]);

    $this->seed(MESDatabaseSeeder::class);
    $this->seed(MESDatabaseSeeder::class);

    $roles = Role::query()->where('name', MESDatabaseSeeder::MACHINE_AGENT_ROLE)->get();

    expect(MESDatabaseSeeder::MACHINE_AGENT_ROLE)->toBe('mes-machine-agent')
        ->and($roles)->toHaveCount(1)
        ->and($roles->sole()->guard_name)->toBe('api')
        ->and($roles->sole()->permissions()->pluck('name')->all())->toBe([PermissionName::forClass(MachineMessage::class, 'insert')])
        ->and($roles->sole()->permissions()->pluck('guard_name')->all())->toBe(['api']);
});

it('runs after the permission refresh in the seed graph, so the machine agent role finds its permissions', function (): void {
    $order = array_map(static fn (SeedNode $node): string => $node->seederClass, app(SeedGraphBuilder::class)->build());
    $positions = array_flip($order);

    expect(MESDatabaseSeeder::dependsOn())->toContain(PermissionRefreshSeeder::class)
        ->and($positions)->toHaveKeys([PermissionRefreshSeeder::class, MESDatabaseSeeder::class])
        ->and($positions[PermissionRefreshSeeder::class])->toBeLessThan($positions[MESDatabaseSeeder::class]);
});
