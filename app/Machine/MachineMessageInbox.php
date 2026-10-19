<?php

declare(strict_types=1);

namespace Modules\MES\Machine;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Modules\MES\Enums\MachineIncidentType;
use Modules\MES\Enums\MachineMessageStatus;
use Modules\MES\Enums\MachineTransport;
use Modules\MES\Enums\MESTables;
use Modules\MES\Jobs\ProcessMachineMessageJob;
use Modules\MES\Machine\Data\InboxResult;
use Modules\MES\Machine\Data\MessageMeta;
use Modules\MES\Machine\Normalizers\NormalizerRegistry;
use Modules\MES\Machine\Normalizers\UnreadableMachinePayload;
use Modules\MES\Machine\Support\IdempotentWriter;
use Modules\MES\Models\MachineMessage;
use Modules\MES\Models\MachineSource;

/**
 * The single entry of machine messages, for HTTP and for the MQTT bridge. It stores the raw
 * message durably and queues its processing; no domain logic runs here.
 */
final class MachineMessageInbox
{
    private const int MESSAGE_ID_LENGTH = 128;

    /**
     * How long a first late message is remembered while waiting for the next one.
     */
    private const int CLOCK_BEHIND_MEMORY_SECONDS = 86_400;

    public function __construct(
        private readonly NormalizerRegistry $registry,
        private readonly IdempotentWriter $writer,
        private readonly MachineIncidentRecorder $incidents,
    ) {}

    /**
     * @throws UnreadableMachinePayload when the source's normaliser cannot read the payload.
     */
    public function accept(MachineSource $source, string $payload, MachineTransport $transport): InboxResult
    {
        $meta = $this->fitMessageId($this->registry->for($source)->meta($source, $payload));
        $received_at = now();

        $inserted = $this->writer->insert(new MachineMessage()->getConnection(), MESTables::MachineMessages->value, [
            'company_id' => $source->company_id,
            'source_id' => $source->id,
            'message_id' => $meta->message_id,
            'source_seq' => $meta->source_seq,
            'transport' => $transport->value,
            'payload' => $payload,
            'received_at' => $received_at,
            'status' => MachineMessageStatus::Pending->value,
            'attempts' => 0,
            'created_at' => $received_at,
            'updated_at' => $received_at,
        ]);

        $message = MachineMessage::query()
            ->withoutGlobalScopes()
            ->where('source_id', $source->id)
            ->where('message_id', $meta->message_id)
            ->firstOrFail();

        if (! $inserted) {
            return new InboxResult(true, $message);
        }

        MachineSource::query()->withoutGlobalScopes()->toBase()->where('id', $source->id)->update(['last_seen_at' => $received_at]);
        $this->checkSequence($source, $meta);
        $this->checkClock($source, $meta, $transport);

        ProcessMachineMessageJob::dispatch($message->id, $source->id);

        return new InboxResult(false, $message);
    }

    /**
     * An id longer than the column is replaced by a hash of itself, the same each time, so a resend still collapses.
     */
    private function fitMessageId(MessageMeta $meta): MessageMeta
    {
        return mb_strlen($meta->message_id) <= self::MESSAGE_ID_LENGTH
            ? $meta
            : new MessageMeta('sha1:' . sha1($meta->message_id), $meta->source_seq, $meta->sent_at);
    }

    private function checkSequence(MachineSource $source, MessageMeta $meta): void
    {
        if ($meta->source_seq === null) {
            return;
        }

        $last = MachineSource::query()->withoutGlobalScopes()->toBase()->where('id', $source->id)->value('last_seq');

        if (is_numeric($last) && $meta->source_seq > (int) $last + 1) {
            $this->incidents->record($source, MachineIncidentType::SeqGap, ['expected' => (int) $last + 1, 'received' => $meta->source_seq]);
        }

        MachineSource::query()->withoutGlobalScopes()->toBase()
            ->where('id', $source->id)
            ->where(static fn ($query) => $query->whereNull('last_seq')->orWhere('last_seq', '<', $meta->source_seq))
            ->update(['last_seq' => $meta->source_seq]);
    }

    /**
     * A sender clock ahead of the server is reported at the first message. A clock behind is reported only when
     * two consecutive new messages are late: one late message alone is what an agent's identical resend after an
     * outage looks like, since its `sent_at` is the time the batch was sealed. Over MQTT a clock behind is not
     * checked at all: after an outage of the bridge the broker's persistent session delivers a backlog in a row,
     * every message old by design, so lateness says nothing about the sender's clock there.
     */
    private function checkClock(MachineSource $source, MessageMeta $meta, MachineTransport $transport): void
    {
        if (! $meta->sent_at instanceof CarbonImmutable) {
            return;
        }

        $offset = now()->diffInSeconds($meta->sent_at, false);

        if ($offset < 0 && $transport === MachineTransport::Mqtt) {
            return;
        }

        $skew = (int) round(abs($offset));
        $behind_key = 'mes:machine:clock-behind:' . $source->id;

        if ($skew > config()->integer('mes.machine.clock_skew_seconds')) {
            if ($offset < 0 && Cache::add($behind_key, true, self::CLOCK_BEHIND_MEMORY_SECONDS)) {
                return;
            }

            // One open incident while the skew lasts, not one per message.
            $this->incidents->recordOnce($source, MachineIncidentType::ClockSkew, [
                'sent_at' => $meta->sent_at->toIso8601ZuluString('millisecond'),
                'received_at' => now()->toIso8601ZuluString('millisecond'),
                'skew_seconds' => $skew,
            ]);

            return;
        }

        Cache::forget($behind_key);
        $this->incidents->resolve($source, MachineIncidentType::ClockSkew);
    }
}
