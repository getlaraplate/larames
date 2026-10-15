<?php

declare(strict_types=1);

namespace Modules\MES\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Core\Http\Controllers\Controller;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\MES\Enums\MachineIncidentType;
use Modules\MES\Machine\MachineIncidentRecorder;
use Modules\MES\Machine\MachineSourceResolver;
use Modules\MES\Models\MachineSource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the caller of a machine route into the machine source it speaks for.
 *
 * Runs after Core's API authentication, which already answered a missing, invalid or expired token (`401`)
 * and an address outside the token's networks (`403`). Declared with the model and the operation of the route,
 * e.g. `ResolveMachineSource::class . ':' . MachineMessage::class . ',insert'`, it checks the `api`
 * permission the way the CRUD routes do (the account's role and the token's abilities; `401` for the anonymous
 * user, `403` for an authenticated one), resolves the source of the service account (`403` when there is none)
 * and refuses an inactive source (`403`). A refusal that names a source becomes one `auth_failure` incident per
 * source every five minutes, so a misconfigured agent cannot flood the incidents; refusals are logged without the
 * token or the body. On success the source is the request attribute {@see self::REQUEST_ATTRIBUTE}.
 */
final readonly class ResolveMachineSource
{
    public const string REQUEST_ATTRIBUTE = 'machine_source';

    private const int INCIDENT_INTERVAL_SECONDS = 300;

    private const int LOG_INTERVAL_SECONDS = 60;

    public function __construct(
        private AuthorizationService $authorization,
        private MachineSourceResolver $sources,
        private MachineIncidentRecorder $incidents,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $model  the model class whose table names the permission
     * @param  string  $operation  the CRUD operation of the permission
     */
    public function handle(Request $request, Closure $next, string $model, string $operation): Response
    {
        if (! is_a($model, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('The machine route names [%s], which is not a model.', $model));
        }

        $request->attributes->remove(self::REQUEST_ATTRIBUTE);
        $user = $request->user();
        $source = $this->sources->forUser($user);

        try {
            $this->authorization->ensurePermissionForClass($request, $model, $operation);
        } catch (AuthorizationException) {
            $status = self::authorizationFailureStatus($request);

            if ($source instanceof MachineSource) {
                $this->recordRefusal($source, 'missing_permission');
            }

            return $status === Response::HTTP_UNAUTHORIZED
                ? response()->json(['message' => 'Unauthenticated.'], $status, ['WWW-Authenticate' => 'Bearer'])
                : response()->json(['message' => 'Forbidden.'], $status);
        }

        if (! $source instanceof MachineSource) {
            $this->logCallerWithoutSource($user?->getAuthIdentifier());

            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (! $source->is_active) {
            $this->recordRefusal($source, 'inactive_source');

            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $source);

        return $next($request);
    }

    /**
     * The status Core's CRUD routes answer a refused permission with ({@see Controller::authorizationFailureStatus()}),
     * asked of Core rather than copied, so the machine routes cannot drift from it.
     */
    private static function authorizationFailureStatus(Request $request): int
    {
        return new class extends Controller
        {
            public function statusFor(Request $request): int
            {
                return $this->authorizationFailureStatus($request);
            }
        }->statusFor($request);
    }

    private function recordRefusal(MachineSource $source, string $reason): void
    {
        if (Cache::add("mes:machine:auth-failure-source:{$source->id}", true, self::INCIDENT_INTERVAL_SECONDS)) {
            $this->incidents->record($source, MachineIncidentType::AuthFailure, ['reason' => $reason]);
            Log::warning('Machine source request refused.', ['source_id' => $source->id, 'reason' => $reason]);
        }
    }

    private function logCallerWithoutSource(mixed $user_id): void
    {
        if (Cache::add('mes:machine:no-source-user:' . (is_scalar($user_id) ? (string) $user_id : ''), true, self::LOG_INTERVAL_SECONDS)) {
            Log::warning('Machine source request refused: the account speaks for no source.', ['user_id' => $user_id]);
        }
    }
}
