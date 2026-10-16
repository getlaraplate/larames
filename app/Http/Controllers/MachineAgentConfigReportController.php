<?php

declare(strict_types=1);

namespace Modules\MES\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Modules\MES\Http\Middleware\ResolveMachineSource;
use Modules\MES\Machine\MachineAgentConfigService;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineSource;

/**
 * Records the outcome of one attempt of the caller's agent to apply a configuration version:
 * `{version, outcome, errors, agent_version}`. `version` must be a version published for the caller's source;
 * `errors` is null or a list of at most {@see MachineAgentConfigService::MAX_REPORT_ERRORS} strings of at most
 * {@see MachineAgentConfigService::MAX_REPORT_ERROR_LENGTH} characters. `201` once stored, `422` otherwise, with no
 * row and no incident.
 */
final class MachineAgentConfigReportController extends Controller
{
    public function __invoke(Request $request, MachineAgentConfigService $configs): JsonResponse
    {
        $source = $request->attributes->get(ResolveMachineSource::REQUEST_ATTRIBUTE);

        if (! $source instanceof MachineSource) {
            throw new LogicException('The report route runs without ' . ResolveMachineSource::class . ', which resolves its source.');
        }

        $validator = Validator::make($request->json()->all(), [
            'version' => ['required', 'integer:strict', 'min:1', Rule::exists(MachineAgentConfig::class, 'version')->where('source_id', $source->id)],
            'outcome' => ['required', 'string', Rule::enum(MachineAgentConfigOutcome::class)],
            'errors' => ['nullable', 'list', 'max:' . MachineAgentConfigService::MAX_REPORT_ERRORS],
            'errors.*' => ['string', 'max:' . MachineAgentConfigService::MAX_REPORT_ERROR_LENGTH],
            'agent_version' => ['required', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'The report is invalid.', 'errors' => $validator->errors()->toArray()], 422);
        }

        /** @var array{version: int, outcome: string, errors?: list<string>|null, agent_version: string} $report */
        $report = $validator->validated();

        $stored = $configs->recordReport(
            $source,
            $report['version'],
            MachineAgentConfigOutcome::from($report['outcome']),
            $report['errors'] ?? null,
            $report['agent_version'],
        );

        return response()->json(['status' => 'recorded', 'id' => $stored->id], 201);
    }
}
