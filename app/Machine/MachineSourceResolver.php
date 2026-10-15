<?php

declare(strict_types=1);

namespace Modules\MES\Machine;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Models\User;
use Modules\MES\Models\MachineSource;

/**
 * Turns an authenticated caller into the machine source it speaks for. Only a service account resolves a
 * source, so a regular user's token never reaches the machine routes even when a row names that user. The
 * company scope does not apply (the agent's request carries no company context), so every global scope is
 * dropped and the soft-delete condition is put back on the model's own column: a soft-deleted source is never
 * resolved. Whether the source is active is left to the caller, which owns the answer and the incident.
 */
final class MachineSourceResolver
{
    public function forUser(?Authenticatable $user): ?MachineSource
    {
        if (! $user instanceof User || ! $user->isServiceAccount()) {
            return null;
        }

        $query = MachineSource::query()->withoutGlobalScopes();

        return $query
            ->where('user_id', $user->getKey())
            ->whereNull($query->getModel()->getQualifiedDeletedAtColumn())
            ->first();
    }
}
