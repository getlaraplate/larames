<?php

declare(strict_types=1);

namespace Modules\MES\Machine;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Modules\MES\Enums\MachineIncidentType;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineAgentConfigReport;
use Modules\MES\Models\MachineSource;

/**
 * Publishes, activates and records the outcome of the `laraplate-edge-config/1` documents of the machine sources.
 *
 * Versions only grow: the agent refuses a document older than the one it applied, so the backend numbers every
 * published document `max + 1` per source, writes that number into the document's `version` field, and
 * "activating an earlier version" publishes its document again as the next version. The document is checked for
 * structure only ({@see self::validate()}, mirroring `resources/edge/laraplate-edge-config-1.schema.json`); driver
 * fields are never interpreted. A failed apply the agent reports opens a `config_rejected` incident.
 */
final readonly class MachineAgentConfigService
{
    public const string SCHEMA = 'laraplate-edge-config/1';

    /**
     * The most error lines one report may carry, and the longest line: they bound the report row and the incident.
     */
    public const int MAX_REPORT_ERRORS = 100;

    public const int MAX_REPORT_ERROR_LENGTH = 1000;

    public function __construct(private MachineIncidentRecorder $incidents) {}

    /**
     * Stores the document as the source's next version and makes it the active one.
     *
     * @param  array<string, mixed>  $document
     *
     * @throws ValidationException under the `document` key, with the errors of {@see self::validate()}
     */
    public function publish(MachineSource $source, array $document, ?User $author = null): MachineAgentConfig
    {
        $errors = $this->validate($document);

        if ($errors !== []) {
            throw ValidationException::withMessages(['document' => $errors]);
        }

        return $source->getConnection()->transaction(function () use ($source, $document, $author): MachineAgentConfig {
            MachineSource::query()->withoutGlobalScopes()->whereKey($source->getKey())->lockForUpdate()->first();

            $latest = MachineAgentConfig::query()->withoutGlobalScopes()->where('source_id', $source->id)->max('version');
            $version = (is_numeric($latest) ? (int) $latest : 0) + 1;
            $document['version'] = $version;

            $config = MachineAgentConfig::query()->create([
                'company_id' => $source->company_id,
                'source_id' => $source->id,
                'version' => $version,
                'document' => $document,
                'created_by' => $author?->getKey(),
            ]);

            MachineSource::query()->withoutGlobalScopes()->whereKey($source->getKey())->update(['active_config_id' => $config->id]);
            $source->forceFill(['active_config_id' => $config->id])->syncOriginalAttribute('active_config_id');
            $source->unsetRelation('activeConfig');

            return $config;
        });
    }

    /**
     * Makes a version the active one. The active version is returned as it is; any other is published again as the
     * source's next version, so a polling agent always receives a higher version number.
     */
    public function activate(MachineAgentConfig $config, ?User $author = null): MachineAgentConfig
    {
        $source = MachineSource::query()->withoutGlobalScopes()->findOrFail($config->source_id);

        if ($source->active_config_id === $config->id) {
            return $config;
        }

        return $this->publish($source, $config->document, $author);
    }

    /**
     * The structural errors of a document, empty when it may be published: the top-level keys, the schema name, an
     * integer version when present (the backend writes it on publish), and `inputs`, `outputs` and the optional
     * `plugins` as lists of objects, each with a non-empty string `id`.
     *
     * @param  array<mixed>  $document
     * @return list<string>
     */
    public function validate(array $document): array
    {
        $errors = [];

        foreach (array_keys($document) as $key) {
            if (! in_array($key, ['schema', 'version', 'inputs', 'outputs', 'plugins'], true)) {
                $errors[] = "The key {$key} is not part of a " . self::SCHEMA . ' document.';
            }
        }

        $validator = Validator::make($document, [
            'schema' => ['required', Rule::in([self::SCHEMA])],
            'version' => ['sometimes', 'integer:strict', 'min:1'],
            'inputs' => ['present', 'list'],
            'outputs' => ['present', 'list'],
            'plugins' => ['sometimes', 'list'],
            'inputs.*' => ['array'],
            'outputs.*' => ['array'],
            'plugins.*' => ['array'],
            'inputs.*.id' => ['required', 'string', 'min:1'],
            'outputs.*.id' => ['required', 'string', 'min:1'],
            'plugins.*.id' => ['required', 'string', 'min:1'],
        ]);

        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = "{$field}: {$message}";
            }
        }

        return $errors;
    }

    /**
     * Records the outcome the source's agent reported for one of the source's versions; a rejection or a rollback
     * opens one `config_rejected` incident with the version, the outcome and the errors.
     *
     * @param  list<string>|null  $errors
     */
    public function recordReport(MachineSource $source, int $version, MachineAgentConfigOutcome $outcome, ?array $errors, string $agent_version): MachineAgentConfigReport
    {
        return $source->getConnection()->transaction(function () use ($source, $version, $outcome, $errors, $agent_version): MachineAgentConfigReport {
            $report = MachineAgentConfigReport::query()->create([
                'company_id' => $source->company_id,
                'source_id' => $source->id,
                'config_version' => $version,
                'outcome' => $outcome->value,
                'errors' => $errors,
                'agent_version' => $agent_version,
                'reported_at' => now(),
            ]);

            if ($outcome->isFailure()) {
                $this->incidents->record($source, MachineIncidentType::ConfigRejected, [
                    'version' => $version,
                    'outcome' => $outcome->value,
                    'errors' => $errors,
                ]);
            }

            return $report;
        });
    }
}
