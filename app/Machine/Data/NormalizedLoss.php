<?php

declare(strict_types=1);

namespace Modules\MES\Machine\Data;

use Carbon\CarbonImmutable;

/**
 * A loss marker of the agent: samples it dropped before they could be sent, over a time range.
 */
final readonly class NormalizedLoss
{
    /**
     * @param  string  $message_id  the envelope that carried the marker
     * @param  string  $priority  `critical` or `bulk`
     * @param  string  $reason  `buffer_full` or `rejected_invalid`
     */
    public function __construct(
        public string $message_id,
        public string $priority,
        public CarbonImmutable $from_ts,
        public CarbonImmutable $to_ts,
        public int $samples,
        public string $reason,
    ) {}
}
