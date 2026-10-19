<?php

declare(strict_types=1);

namespace Modules\MES\Machine;

use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Core\Models\User;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Modules\MES\Enums\MachineIncidentType;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineAgentConfigReport;
use Modules\MES\Models\MachineIncident;
use Modules\MES\Models\MachineSource;
use stdClass;

/**
 * Publishes, activates and records the outcome of the `laraplate-edge-config/1` documents of the machine sources.
 *
 * Versions only grow: the agent refuses a document older than the one it applied, so the backend numbers every
 * published document with the next number of its source, writes that number into the document's `version` field, and
 * "activating an earlier version" publishes its document again as the next version. The document is checked for
 * structure only ({@see self::validate()}, mirroring `resources/edge/laraplate-edge-config-1.schema.json`); driver
 * fields are never interpreted. A failed apply the agent reports opens a `config_rejected` incident, once per open source, version and outcome.
 */
final readonly class MachineAgentConfigService
{
    public const string SCHEMA = 'laraplate-edge-config/1';

    /**
     * The most error lines one report may carry, and the longest line: they bound the report row and the incident.
     */
    public const int MAX_REPORT_ERRORS = 100;

    public const int MAX_REPORT_ERROR_LENGTH = 1000;

    /**
     * @var list<string>
     */
    private const array TOP_LEVEL_KEYS = ['schema', 'version', 'inputs', 'outputs', 'plugins'];

    /**
     * The component lists of a document, and whether each is required.
     *
     * @var array<string, bool>
     */
    private const array COMPONENT_LISTS = ['inputs' => true, 'outputs' => true, 'plugins' => false];

    public function __construct(private MachineIncidentRecorder $incidents) {}

    /**
     * Stores the document as the source's next version and makes it the active one.
     *
     * The document is a JSON text or the tree `json_decode($text, false)` gives, so an empty object stays `{}` and an
     * object with numeric keys stays an object: it is stored and served as the same JSON value (not byte for byte: MySQL's
     * `JSON` column may reorder object keys), plus its `version`. The given tree is never changed. The number comes from
     * the source's `last_config_version`, read and raised under the lock of the source row, so it never repeats, even
     * inside an outer transaction or after a version row is gone.
     *
     * @throws ValidationException under the `document` key, with the errors of {@see self::validate()}
     */
    public function publish(MachineSource $source, stdClass|string $document, ?User $author = null): MachineAgentConfig
    {
        $tree = self::tree($document);
        $errors = $tree instanceof stdClass ? $this->structuralErrors($tree) : [$tree];

        if (! $tree instanceof stdClass || $errors !== []) {
            throw ValidationException::withMessages(['document' => $errors]);
        }

        return $source->getConnection()->transaction(function () use ($source, $tree, $author): MachineAgentConfig {
            $locked = MachineSource::query()->withoutGlobalScopes()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $version = $locked->last_config_version + 1;
            $tree->version = $version;

            $config = MachineAgentConfig::query()->create([
                'company_id' => $source->company_id,
                'source_id' => $source->id,
                'version' => $version,
                'document' => $tree,
                'created_by' => $author?->getKey(),
            ]);

            $pointers = ['active_config_id' => $config->id, 'last_config_version' => $version];
            MachineSource::query()->withoutGlobalScopes()->whereKey($source->getKey())->update($pointers);
            $source->forceFill($pointers)->syncOriginalAttributes(array_keys($pointers));
            $source->unsetRelation('activeConfig');

            return $config;
        });
    }

    /**
     * Makes a version the active one. The active version is returned as it is; any other is published again as the
     * source's next version, document untouched but for its `version`, so a polling agent always receives a higher
     * version number.
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
     * The structural errors of a document (JSON text or tree, as {@see self::publish()} takes it), empty when it may
     * be published: a JSON object with only the known top-level keys, the schema name, an integer version of at least
     * 1 when present (the backend writes it on publish), and `inputs`, `outputs` and the optional `plugins` as JSON
     * arrays of objects, each with a non-empty string `id`. Each error starts with the path it names.
     *
     * @return list<string>
     */
    public function validate(stdClass|string $document): array
    {
        $tree = self::tree($document);

        return $tree instanceof stdClass ? $this->structuralErrors($tree) : [$tree];
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

            if ($outcome->isFailure() && ! $this->hasOpenRejection($source, $version, $outcome)) {
                $this->incidents->record($source, MachineIncidentType::ConfigRejected, [
                    'version' => $version,
                    'outcome' => $outcome->value,
                    'errors' => $errors,
                ]);
            }

            return $report;
        });
    }

    /**
     * Whether an incident for this source, version and outcome is still open: an agent that retries a report whose
     * answer it did not see, or reports the same failure again, must not open (and notify) it twice. The reports
     * are all kept.
     */
    private function hasOpenRejection(MachineSource $source, int $version, MachineAgentConfigOutcome $outcome): bool
    {
        return MachineIncident::query()
            ->withoutGlobalScopes()
            ->unresolved()
            ->where('source_id', $source->id)
            ->where('type', MachineIncidentType::ConfigRejected->value)
            ->get()
            ->contains(static fn (MachineIncident $incident): bool => ($incident->detail['version'] ?? null) === $version && ($incident->detail['outcome'] ?? null) === $outcome->value);
    }

    /**
     * A private copy of the document's JSON tree, objects kept as objects; or the error that keeps the document from
     * being one.
     */
    private static function tree(stdClass|string $document): stdClass|string
    {
        try {
            $tree = json_decode(is_string($document) ? $document : json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'document: The document is not valid JSON.';
        }

        return $tree instanceof stdClass ? $tree : 'document: The document must be a JSON object.';
    }

    /**
     * @return list<string>
     */
    private function structuralErrors(stdClass $tree): array
    {
        $errors = [];

        foreach (array_keys(get_object_vars($tree)) as $key) {
            if (! in_array($key, self::TOP_LEVEL_KEYS, true)) {
                $errors[] = "{$key}: The key is not part of a " . self::SCHEMA . ' document.';
            }
        }

        if (($tree->schema ?? null) !== self::SCHEMA) {
            $errors[] = 'schema: The schema must be ' . self::SCHEMA . '.';
        }

        if (property_exists($tree, 'version') && (! is_int($tree->version) || $tree->version < 1)) {
            $errors[] = 'version: The version must be an integer of at least 1.';
        }

        foreach (self::COMPONENT_LISTS as $list => $required) {
            if (! property_exists($tree, $list)) {
                if ($required) {
                    $errors[] = "{$list}: The {$list} field is required.";
                }

                continue;
            }

            $items = $tree->{$list};

            if (! is_array($items) || ! array_is_list($items)) {
                $errors[] = "{$list}: The {$list} must be a JSON array.";

                continue;
            }

            foreach ($items as $index => $item) {
                if (! $item instanceof stdClass) {
                    $errors[] = "{$list}.{$index}: The {$list}.{$index} must be a JSON object.";

                    continue;
                }

                if (! is_string($item->id ?? null) || $item->id === '') {
                    $errors[] = "{$list}.{$index}.id: The {$list}.{$index}.id must be a non-empty string.";
                }
            }
        }

        return $errors;
    }
}
