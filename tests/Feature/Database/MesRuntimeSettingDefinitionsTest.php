<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\MES\Database\Seeders\MESDatabaseSeeder;

it('defines mes runtime settings with current defaults', function (): void {
    $definitions = collect(MESDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');

    expect($definitions->get('lots.number_format')['value'])->toBe('{YEAR}{MONTH}{DAY}-{SEQ}');
});

it('defines the production tunables that were env variables, with the defaults they had', function (string $name, mixed $value, SettingTypeEnum $type): void {
    $definition = collect(MESDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name')->get($name);

    expect($definition)->not->toBeNull()
        ->and($definition['value'])->toBe($value)
        ->and($definition['type'])->toBe($type)
        ->and($definition['choices'])->toBeNull()
        ->and($definition['encrypted'])->toBeFalse()
        ->and($definition['group_name'])->toBe('mes')
        ->and($definition['description'])->toBeString()->not->toBeEmpty()
        ->and(config('mes.' . $name))->toBe($value);
})->with([
    'working minutes per day' => ['production.daily_minutes', 480.0, SettingTypeEnum::Float],
    'default lead time' => ['production.default_lead_time_days', 5, SettingTypeEnum::Integer],
]);
