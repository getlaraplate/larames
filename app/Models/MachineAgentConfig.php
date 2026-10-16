<?php

declare(strict_types=1);

namespace Modules\MES\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Concerns\DeniesGenericCrudWrites;
use Modules\Core\Models\User;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\MES\Database\Factories\MachineAgentConfigFactory;
use Override;

/**
 * One version of the `laraplate-edge-config/1` document of a machine source. The document is stored as published
 * and never interpreted beyond its structure; its `version` field always equals the stored version.
 *
 * Written only by {@see \Modules\MES\Machine\MachineAgentConfigService}, which numbers the versions and validates
 * the structure: the generic CRUD refuses every write, and the `select` permission the machine agents hold reads
 * only their own source's versions through the ACL the MES seeder gives their role.
 *
 * @property int $id
 * @property int $company_id
 * @property int $source_id
 * @property int $version
 * @property array<string, mixed> $document
 * @property ?int $created_by
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
final class MachineAgentConfig extends Model implements RestrictsCrudWrites
{
    /** @use HasFactory<MachineAgentConfigFactory> */
    use BelongsToCompany, DeniesGenericCrudWrites, HasFactory;

    /**
     * @var string
     */
    #[Override]
    protected $table = 'mes_machine_agent_configs';

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'source_id',
        'version',
        'document',
        'created_by',
    ];

    /**
     * @return BelongsTo<MachineSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MachineSource::class, 'source_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return Factory<MachineAgentConfig>
     */
    protected static function newFactory(): Factory
    {
        return MachineAgentConfigFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'document' => 'array',
            'created_by' => 'integer',
        ];
    }
}
