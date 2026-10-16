<?php

declare(strict_types=1);

namespace Modules\MES\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\MES\Database\Factories\Concerns\UsesDefaultCompany;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineSource;
use Override;

/**
 * A stored version as the service writes it. It does not activate the version: use
 * {@see \Modules\MES\Machine\MachineAgentConfigService::publish()} for that.
 *
 * @extends Factory<MachineAgentConfig>
 */
final class MachineAgentConfigFactory extends Factory
{
    use UsesDefaultCompany;

    /**
     * @var class-string<MachineAgentConfig>
     */
    protected $model = MachineAgentConfig::class;

    /**
     * @return array<model-property<MachineAgentConfig>, mixed>
     */
    #[Override]
    public function definition(): array
    {
        $version = fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'company_id' => $this->defaultCompanyId(),
            'source_id' => MachineSource::factory(),
            'version' => $version,
            'document' => [
                'schema' => 'laraplate-edge-config/1',
                'version' => $version,
                'inputs' => [],
                'outputs' => [],
            ],
            'created_by' => null,
        ];
    }
}
