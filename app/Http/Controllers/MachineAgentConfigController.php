<?php

declare(strict_types=1);

namespace Modules\MES\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Modules\MES\Http\Middleware\ResolveMachineSource;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineSource;

/**
 * Serves the active `laraplate-edge-config/1` document of the caller's source as `{version, document}`. With
 * `?version=` equal to the active version it answers `304` with no body, so a polling agent downloads only a new
 * version; any other version, lower or higher, receives the active document. `404` when the source has no active
 * version, `422` when `version` is not a positive integer.
 */
final class MachineAgentConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $source = $request->attributes->get(ResolveMachineSource::REQUEST_ATTRIBUTE);

        if (! $source instanceof MachineSource) {
            throw new LogicException('The configuration route runs without ' . ResolveMachineSource::class . ', which resolves its source.');
        }

        $validator = Validator::make($request->query(), ['version' => ['sometimes', 'filled', 'integer', 'min:1']]);

        if ($validator->fails()) {
            return response()->json(['message' => 'The version is invalid.', 'errors' => $validator->errors()->toArray()], 422);
        }

        $config = $source->active_config_id === null ? null : MachineAgentConfig::query()
            ->withoutGlobalScopes()
            ->where('source_id', $source->id)
            ->whereKey($source->active_config_id)
            ->first();

        if (! $config instanceof MachineAgentConfig) {
            return response()->json(['message' => 'The source has no active configuration.'], 404);
        }

        if ($request->query->has('version') && $config->version === (int) $request->query('version')) {
            return response()->noContent(304);
        }

        return response()->json(['version' => $config->version, 'document' => $config->document]);
    }
}
