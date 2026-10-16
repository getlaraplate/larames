<?php

declare(strict_types=1);

namespace Modules\MES\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\MES\Database\Factories\Concerns\UsesDefaultCompany;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Modules\MES\Models\MachineAgentConfigReport;
use Modules\MES\Models\MachineSource;
use Override;

/**
 * @extends Factory<MachineAgentConfigReport>
 */
final class MachineAgentConfigReportFactory extends Factory
{
    use UsesDefaultCompany;

    /**
     * @var class-string<MachineAgentConfigReport>
     */
    protected $model = MachineAgentConfigReport::class;

    /**
     * @return array<model-property<MachineAgentConfigReport>, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'company_id' => $this->defaultCompanyId(),
            'source_id' => MachineSource::factory(),
            'config_version' => 1,
            'outcome' => MachineAgentConfigOutcome::Applied->value,
            'errors' => null,
            'agent_version' => '0.1.0',
            'reported_at' => now(),
        ];
    }
}
