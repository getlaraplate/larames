<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\MES\Enums\MachineIncidentType;
use Modules\MES\Enums\MachineMessageStatus;
use Modules\MES\Enums\MachineTransport;
use Modules\MES\Events\MachineIncidentRecorded;
use Modules\MES\Jobs\ProcessMachineMessageJob;
use Modules\MES\Machine\Data\NormalizedLoss;
use Modules\MES\Machine\MachineIncidentRecorder;
use Modules\MES\Machine\MachineMessageInbox;
use Modules\MES\Machine\MachineMessageProcessor;
use Modules\MES\Machine\MachineMessageReprocessor;
use Modules\MES\Machine\Mqtt\MqttIngest;
use Modules\MES\Machine\Mqtt\MqttMessage;
use Modules\MES\Machine\Normalizers\CanonicalNormalizer;
use Modules\MES\Models\MachineIncident;
use Modules\MES\Models\MachineMessage;
use Modules\MES\Models\MachineSource;
use Modules\MES\Tests\Support\MesTestHelpers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    MesTestHelpers::makeCompany();
    Cache::flush();
    Event::fake([MachineIncidentRecorded::class]);
    config(['mes.queue.connection' => 'sync']);
});

/**
 * @return list<array<string, mixed>>
 */
function twoLosses(): array
{
    return [
        ['priority' => 'bulk', 'from_ts' => '2026-10-05T08:10:00.000Z', 'to_ts' => '2026-10-05T08:12:30.500Z', 'samples' => 120, 'reason' => 'buffer_full'],
        ['priority' => 'critical', 'from_ts' => '2026-10-05T08:13:00Z', 'to_ts' => '2026-10-05T08:13:05Z', 'samples' => 1, 'reason' => 'rejected_invalid'],
    ];
}

/**
 * @param  list<array<string, mixed>>|null  $losses
 */
function lossEnvelope(string $message_id = 'm-loss-1', ?array $losses = null): string
{
    $envelope = [
        'protocol' => 'laraplate-machine/1',
        'message_id' => $message_id,
        'source_seq' => 1,
        'sent_at' => now()->toIso8601ZuluString('millisecond'),
        'devices' => [['device' => 'press-07', 'type' => 'data', 'samples' => [['signal' => 's', 'ts' => now()->toIso8601ZuluString('millisecond'), 'value' => 1]]]],
    ];

    if ($losses !== null) {
        $envelope['losses'] = $losses;
    }

    return json_encode($envelope, JSON_THROW_ON_ERROR);
}

function storedLossMessage(MachineSource $source, string $payload, string $message_id = 'm-loss-1'): MachineMessage
{
    return MachineMessage::factory()->create(['source_id' => $source->id, 'message_id' => $message_id, 'payload' => $payload]);
}

function runLossJob(MachineMessage $message, bool $reprocess = false): void
{
    app()->call([new ProcessMachineMessageJob($message->id, $message->source_id, $reprocess), 'handle']);
}

/**
 * @return Illuminate\Database\Eloquent\Collection<int, MachineIncident>
 */
function droppedIncidents(): Illuminate\Database\Eloquent\Collection
{
    return MachineIncident::query()->where('type', MachineIncidentType::DataDropped->value)->orderBy('occurred_at')->get();
}

it('reads the loss markers of an envelope', function (): void {
    $source = MachineSource::factory()->create();

    $losses = new CanonicalNormalizer()->normalize($source, lossEnvelope('m-1', twoLosses()))->losses;

    expect($losses)->toHaveCount(2)
        ->and($losses[0])->toBeInstanceOf(NormalizedLoss::class)
        ->and($losses[0]->message_id)->toBe('m-1')
        ->and($losses[0]->priority)->toBe('bulk')
        ->and($losses[0]->from_ts->format('Y-m-d\TH:i:s.v\Z'))->toBe('2026-10-05T08:10:00.000Z')
        ->and($losses[0]->to_ts->format('Y-m-d\TH:i:s.v\Z'))->toBe('2026-10-05T08:12:30.500Z')
        ->and($losses[0]->samples)->toBe(120)
        ->and($losses[0]->reason)->toBe('buffer_full')
        ->and(new CanonicalNormalizer()->normalize($source, lossEnvelope())->losses)->toBe([]);
});

it('records one data_dropped incident per loss when a message is processed, dated at the end of the gap', function (): void {
    $source = MachineSource::factory()->create();
    $message = storedLossMessage($source, lossEnvelope('m-loss-1', twoLosses()));

    runLossJob($message);

    $incidents = droppedIncidents();
    expect($incidents)->toHaveCount(2)
        ->and($incidents[0]->source_id)->toBe($source->id)
        ->and($incidents[0]->device_id)->toBeNull()
        ->and($incidents[0]->detail)->toBe(['message_id' => 'm-loss-1', 'priority' => 'bulk', 'from_ts' => '2026-10-05T08:10:00.000Z', 'to_ts' => '2026-10-05T08:12:30.500Z', 'samples' => 120, 'reason' => 'buffer_full'])
        ->and(abs($incidents[0]->occurred_at->diffInMilliseconds(Illuminate\Support\Carbon::parse('2026-10-05T08:12:30.500Z'))))->toBeLessThan(1000)
        ->and($incidents[1]->detail)->toBe(['message_id' => 'm-loss-1', 'priority' => 'critical', 'from_ts' => '2026-10-05T08:13:00.000Z', 'to_ts' => '2026-10-05T08:13:05.000Z', 'samples' => 1, 'reason' => 'rejected_invalid'])
        ->and($incidents[1]->occurred_at->clone()->utc()->format('Y-m-d H:i:s'))->toBe('2026-10-05 08:13:05')
        ->and($message->fresh()->status)->toBe(MachineMessageStatus::Processed);
});

it('records nothing for a message without losses or with an empty list', function (): void {
    $source = MachineSource::factory()->create();

    runLossJob(storedLossMessage($source, lossEnvelope('m-a'), 'm-a'));
    runLossJob(storedLossMessage($source, lossEnvelope('m-b', []), 'm-b'));

    expect(droppedIncidents())->toHaveCount(0);
});

it('records no new incident when the message is reprocessed', function (): void {
    $source = MachineSource::factory()->create();
    $message = storedLossMessage($source, lossEnvelope('m-loss-1', twoLosses()));
    runLossJob($message);

    resolve(MachineMessageReprocessor::class)->reprocess($message->fresh());
    resolve(MachineMessageReprocessor::class)->reprocess($message->fresh());

    expect(droppedIncidents())->toHaveCount(2)
        ->and($message->fresh()->attempts)->toBe(3);
});

it('records no new incident when a retry runs the whole message again', function (): void {
    $source = MachineSource::factory()->create();
    $message = storedLossMessage($source, lossEnvelope('m-loss-1', twoLosses()));
    $normalized = new CanonicalNormalizer()->normalize($source, $message->payload);
    $processor = resolve(MachineMessageProcessor::class);

    $processor->process($source, $normalized, now(), true);
    $processor->process($source, $normalized, now(), false);

    expect(droppedIncidents())->toHaveCount(2);
});

it('keeps the incidents of the same range apart when two different messages report it', function (): void {
    $source = MachineSource::factory()->create();

    runLossJob(storedLossMessage($source, lossEnvelope('m-a', twoLosses()), 'm-a'));
    runLossJob(storedLossMessage($source, lossEnvelope('m-b', twoLosses()), 'm-b'));

    expect(droppedIncidents())->toHaveCount(4);
});

it('takes an incident for a marker only when every field of its detail matches', function (): void {
    $source = MachineSource::factory()->create();
    $loss = twoLosses()[0];
    $other = ['message_id' => 'm-loss-1', ...$loss, 'samples' => 7];
    resolve(MachineIncidentRecorder::class)->record($source, MachineIncidentType::DataDropped, $other, null, Illuminate\Support\Carbon::parse($loss['to_ts'])->setTimezone(now()->getTimezone()));

    runLossJob(storedLossMessage($source, lossEnvelope('m-loss-1', [$loss])));

    expect(droppedIncidents())->toHaveCount(2)
        ->and(droppedIncidents()->pluck('detail.samples')->sort()->values()->all())->toBe([7, 120]);
});

it('records every entry of a message even when two are identical, once each on a rerun', function (): void {
    $source = MachineSource::factory()->create();
    $loss = twoLosses()[0];
    $message = storedLossMessage($source, lossEnvelope('m-loss-1', [$loss, $loss]));

    runLossJob($message);
    runLossJob($message->fresh(), true);

    expect(droppedIncidents())->toHaveCount(2);
});

it('records the losses of a message through the inbox once, and none for a duplicate', function (): void {
    $source = MachineSource::factory()->create();
    $inbox = resolve(MachineMessageInbox::class);

    $first = $inbox->accept($source, lossEnvelope('m-loss-1', twoLosses()), MachineTransport::Http);
    $second = $inbox->accept($source, lossEnvelope('m-loss-1', twoLosses()), MachineTransport::Http);

    expect($first->duplicate)->toBeFalse()
        ->and($second->duplicate)->toBeTrue()
        ->and(droppedIncidents())->toHaveCount(2);
});

it('records the losses of an envelope received through the MQTT bridge', function (): void {
    MachineSource::factory()->mqtt()->create(['code' => 'gw-1', 'mqtt_topic' => null]);
    $ingest = resolve(MqttIngest::class);
    $message = new MqttMessage('laraplate/laraplate-machine/1/gw-1', lossEnvelope('m-loss-1', twoLosses()));

    $ingest->handle($message);
    $ingest->handle($message);

    expect(droppedIncidents())->toHaveCount(2)
        ->and(droppedIncidents()[0]->detail['message_id'])->toBe('m-loss-1');
});

it('refuses an envelope with a malformed loss before storing it', function (): void {
    MachineSource::factory()->mqtt()->create(['code' => 'gw-1', 'mqtt_topic' => null]);
    $broken = twoLosses();
    $broken[0]['samples'] = 0;

    resolve(MqttIngest::class)->handle(new MqttMessage('laraplate/laraplate-machine/1/gw-1', lossEnvelope('m-loss-1', $broken)));

    expect(MachineMessage::query()->count())->toBe(0)
        ->and(droppedIncidents())->toHaveCount(0);
});
