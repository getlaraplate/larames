<?php

declare(strict_types=1);

namespace Modules\MES\Enums;

/**
 * The outcome an edge agent reports for one attempt to apply a configuration version.
 */
enum MachineAgentConfigOutcome: string
{
    case Applied = 'applied';
    case Rejected = 'rejected';
    case RolledBack = 'rolled_back';

    /**
     * Returns an 'in:...' validation rule string for all enum values.
     */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', array_column(self::cases(), 'value'));
    }

    /**
     * Returns all enum values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether the agent kept running another document than the reported one: a refused document and a rollback
     * both leave the new version unapplied.
     */
    public function isFailure(): bool
    {
        return $this !== self::Applied;
    }
}
