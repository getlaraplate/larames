<?php

declare(strict_types=1);

namespace Modules\MES\Machine;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Models\User;
use Modules\MES\Models\MachineSource;

/**
 * Turns an authenticated caller into the machine source it speaks for. Only a service account resolves a
 * source, so a regular user's token never reaches the machine routes even when a row names that user. A
 * soft-deleted source is never resolved; whether the source is active is left to the caller, which owns
 * the answer and the incident.
 */
final class MachineSourceResolver
{
    public function forUser(?Authenticatable $user): ?MachineSource
    {
        if (! $user instanceof User || ! $user->isServiceAccount()) {
            return null;
        }

        return MachineSource::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->whereNull('deleted_at')
            ->first();
    }
}
