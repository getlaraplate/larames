<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Setting;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;
use Modules\MES\Models\Routing;
use Modules\MES\Models\RoutingOperation;
use Modules\MES\Models\WorkCenter;
use Modules\MES\Services\ProductionLeadTimeEstimator;

uses(RefreshDatabase::class);

/**
 * Stores an operator's value on a seeded MES setting and applies the overlay the way a request does.
 */
function mes_store_setting(string $name, mixed $value): void
{
    Setting::query()->withoutGlobalScopes()
        ->where('module', 'MES')->where('name', $name)
        ->update(['value' => json_encode($value)]);

    app(SettingsCacheCoordinator::class)->flushAll();
    app()->forgetScopedInstances();

    app(DatabaseConfigOverlay::class)->applyFromDatabase(app(PerModelSettingResolver::class));
}

/**
 * An item whose active routing totals `$standard_minutes` for a quantity of one.
 */
function mes_item_with_routing_minutes(float $standard_minutes): int
{
    $routing = Routing::factory()->create();

    RoutingOperation::factory()->create([
        'routing_id' => $routing->id,
        'work_center_id' => WorkCenter::factory()->create(['company_id' => $routing->company_id])->id,
        'setup_time_minutes' => 0,
        'cycle_time_minutes' => $standard_minutes,
    ]);

    return $routing->item_id;
}

beforeEach(function (): void {
    $this->seed(MESDatabaseSeeder::class);
    $this->from = CarbonImmutable::now();
});

it('turns the standard minutes into days with the stored working minutes per day', function (): void {
    $item_id = mes_item_with_routing_minutes(1000.0);

    $default = resolve(ProductionLeadTimeEstimator::class)->estimate($item_id, 1.0, $this->from);

    mes_store_setting('production.daily_minutes', 960.0);

    $stored = resolve(ProductionLeadTimeEstimator::class)->estimate($item_id, 1.0, $this->from);

    expect($default['end']->equalTo($this->from->addWeekdays(3)))->toBeTrue()
        ->and($stored['end']->equalTo($this->from->addWeekdays(2)))->toBeTrue();
});

it('reads a stored zero of working minutes per day as one minute', function (): void {
    $item_id = mes_item_with_routing_minutes(3.0);

    mes_store_setting('production.daily_minutes', 0.0);

    $estimate = resolve(ProductionLeadTimeEstimator::class)->estimate($item_id, 1.0, $this->from);

    expect($estimate['end']->equalTo($this->from->addWeekdays(3)))->toBeTrue();
});

it('applies the stored default lead time when the item has no routing', function (): void {
    $default = resolve(ProductionLeadTimeEstimator::class)->estimate(999_999, 1.0, $this->from);

    mes_store_setting('production.default_lead_time_days', 9);

    $stored = resolve(ProductionLeadTimeEstimator::class)->estimate(999_999, 1.0, $this->from);

    expect($default['end']->equalTo($this->from->addWeekdays(5)))->toBeTrue()
        ->and($stored['end']->equalTo($this->from->addWeekdays(9)))->toBeTrue();
});

it('reads a stored zero default lead time as one day', function (): void {
    mes_store_setting('production.default_lead_time_days', 0);

    $estimate = resolve(ProductionLeadTimeEstimator::class)->estimate(999_999, 1.0, $this->from);

    expect($estimate['end']->equalTo($this->from->addWeekdays(1)))->toBeTrue();
});
