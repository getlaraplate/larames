<?php

declare(strict_types=1);

namespace Modules\MES\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Concerns\DeniesGenericCrudWrites;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\MES\Database\Factories\MachineAgentConfigReportFactory;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Override;

/**
 * One attempt of a machine source's edge agent to apply a configuration version, as the agent reported it.
 *
 * Written only by the report route through {@see \Modules\MES\Machine\MachineAgentConfigService}: the generic CRUD
 * refuses every write, so the `insert` permission the machine agents hold opens the report route alone.
 *
 * @property int $id
 * @property int $company_id
 * @property int $source_id
 * @property int $config_version
 * @property MachineAgentConfigOutcome $outcome
 * @property list<string>|null $errors
 * @property string $agent_version
 * @property \Illuminate\Support\Carbon $reported_at
 */
final class MachineAgentConfigReport extends Model implements RestrictsCrudWrites
{
    /** @use HasFactory<MachineAgentConfigReportFactory> */
    use BelongsToCompany, DeniesGenericCrudWrites, HasFactory;

    /**
     * @var string
     */
    #[Override]
    protected $table = 'mes_machine_agent_config_reports';

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'source_id',
        'config_version',
        'outcome',
        'errors',
        'agent_version',
        'reported_at',
    ];

    /**
     * @return BelongsTo<MachineSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MachineSource::class, 'source_id');
    }

    /**
     * @return Factory<MachineAgentConfigReport>
     */
    protected static function newFactory(): Factory
    {
        return MachineAgentConfigReportFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'config_version' => 'integer',
            'outcome' => MachineAgentConfigOutcome::class,
            'errors' => 'array',
            'reported_at' => 'datetime',
        ];
    }
}
