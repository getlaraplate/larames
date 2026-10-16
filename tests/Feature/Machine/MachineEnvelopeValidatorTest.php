<?php

declare(strict_types=1);

use Modules\MES\Machine\Protocol\MachineEnvelopeValidator;

/**
 * @return array<string, array{string}>
 */
function protocolFixtures(string $directory): array
{
    $files = glob(dirname(__DIR__, 2) . "/Fixtures/machine-protocol/{$directory}/*.json") ?: [];
    $cases = [];

    foreach ($files as $file) {
        $cases[basename($file, '.json')] = [$file];
    }

    return $cases;
}

function loadEnvelope(string $file): array
{
    return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
}

it('accepts every valid envelope fixture', function (string $file): void {
    expect(resolve(MachineEnvelopeValidator::class)->validate(loadEnvelope($file)))->toBe([]);
})->with(protocolFixtures('valid'));

it('rejects every invalid envelope fixture', function (string $file): void {
    expect(resolve(MachineEnvelopeValidator::class)->validate(loadEnvelope($file)))->not->toBeEmpty();
})->with(protocolFixtures('invalid'));

it('ships the fixtures it is tested with', function (): void {
    expect(protocolFixtures('valid'))->toHaveCount(4)
        ->and(protocolFixtures('invalid'))->toHaveCount(23);
});

it('counts the samples of an envelope', function (): void {
    $envelope = loadEnvelope(dirname(__DIR__, 2) . '/Fixtures/machine-protocol/valid/with-context.json');

    expect(resolve(MachineEnvelopeValidator::class)->sampleCount($envelope))->toBe(3);
});

it('names the loss field of the envelope in the validator errors', function (string $file, string $field): void {
    expect(resolve(MachineEnvelopeValidator::class)->validate(loadEnvelope($file)))->toHaveKey($field);
})->with([
    'unknown priority' => [dirname(__DIR__, 2) . '/Fixtures/machine-protocol/invalid/loss-unknown-priority.json', 'losses.0.priority'],
    'zero samples' => [dirname(__DIR__, 2) . '/Fixtures/machine-protocol/invalid/loss-zero-samples.json', 'losses.0.samples'],
    'bad from_ts' => [dirname(__DIR__, 2) . '/Fixtures/machine-protocol/invalid/loss-bad-from-ts.json', 'losses.0.from_ts'],
    'missing reason' => [dirname(__DIR__, 2) . '/Fixtures/machine-protocol/invalid/loss-missing-key.json', 'losses.0.reason'],
    'extra key' => [dirname(__DIR__, 2) . '/Fixtures/machine-protocol/invalid/loss-extra-key.json', 'losses.0'],
]);

it('refuses devices and losses sent as a JSON object map, as the schema does', function (string $file, string $field): void {
    expect(resolve(MachineEnvelopeValidator::class)->validate(loadEnvelope($file)))->toHaveKey($field);
})->with([
    'devices' => [dirname(__DIR__, 2) . '/Fixtures/machine-protocol/invalid/devices-object-map.json', 'devices'],
    'losses' => [dirname(__DIR__, 2) . '/Fixtures/machine-protocol/invalid/losses-object-map.json', 'losses'],
]);

it('publishes the schema of the protocol', function (): void {
    $schema = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/resources/protocol/laraplate-machine-1.schema.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($schema['$id'])->toBe('https://laraplate.dev/schemas/laraplate-machine-1.schema.json')
        ->and($schema['properties']['protocol']['const'])->toBe('laraplate-machine/1');
});

it('publishes the loss markers in the schema as an optional list with a closed shape', function (): void {
    $schema = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/resources/protocol/laraplate-machine-1.schema.json'), true, 512, JSON_THROW_ON_ERROR);
    $loss = $schema['$defs']['loss'];

    expect($schema['required'])->not->toContain('losses')
        ->and($schema['properties']['losses']['items']['$ref'])->toBe('#/$defs/loss')
        ->and($loss['required'])->toBe(['priority', 'from_ts', 'to_ts', 'samples', 'reason'])
        ->and($loss['additionalProperties'])->toBeFalse()
        ->and($loss['properties']['priority']['enum'])->toBe(['critical', 'bulk'])
        ->and($loss['properties']['samples']['minimum'])->toBe(1)
        ->and($loss['properties']['reason']['enum'])->toBe(['buffer_full', 'rejected_invalid']);
});
