<?php

declare(strict_types=1);

namespace Modules\MES\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\MES\Database\Factories\OperatorLogFactory;
use Modules\MES\Enums\OperatorLogAction;
use Override;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $production_order_operation_id
 * @property int|null $shift_instance_id
 * @property OperatorLogAction $action
 * @property \Carbon\CarbonImmutable $logged_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
final class OperatorLog extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    #[Override]
    protected $table = 'mes_operator_logs';

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = [
        'user_id',
        'production_order_operation_id',
        'shift_instance_id',
        'action',
        'logged_at',
    ];

    /**
     * @return BelongsTo<ProductionOrderOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    /**
     * @return BelongsTo<ShiftInstance, $this>
     */
    public function shiftInstance(): BelongsTo
    {
        return $this->belongsTo(ShiftInstance::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory<OperatorLog>
     */
    protected static function newFactory(): Factory
    {
        return OperatorLogFactory::new();
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'action' => OperatorLogAction::class,
            'logged_at' => 'datetime',
        ];
    }
}
