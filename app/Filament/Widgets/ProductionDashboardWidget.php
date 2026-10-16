<?php

declare(strict_types=1);

namespace Modules\MES\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Modules\MES\Enums\NonConformanceStatus;
use Modules\MES\Enums\ProductionOrderOperationStatus;
use Modules\MES\Enums\ProductionOrderStatus;
use Modules\MES\Models\NonConformance;
use Modules\MES\Models\ProductionOrder;
use Modules\MES\Models\ProductionOrderOperation;
use Override;

/**
 * Headline MES production KPIs. Per the module's API decision, aggregate KPIs
 * (open orders, running operations, open non-conformances) are surfaced as a
 * Filament widget rather than through dedicated read routes.
 */
final class ProductionDashboardWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 30;

    #[Override]
    protected ?string $heading = 'MES';

    #[Override]
    protected static bool $isLazy = true;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $data = Cache::remember('filament.dashboard.mes_production', 60, static fn (): array => [
            'open_orders' => ProductionOrder::query()
                ->whereIn('status', [
                    ProductionOrderStatus::Draft->value,
                    ProductionOrderStatus::Released->value,
                    ProductionOrderStatus::InProgress->value,
                ])
                ->count(),
            'running_operations' => ProductionOrderOperation::query()
                ->where('status', ProductionOrderOperationStatus::InProgress->value)
                ->count(),
            'completed_orders' => ProductionOrder::query()
                ->where('status', ProductionOrderStatus::Completed->value)
                ->count(),
            'open_non_conformances' => NonConformance::query()
                ->whereIn('status', [
                    NonConformanceStatus::Open->value,
                    NonConformanceStatus::UnderReview->value,
                ])
                ->count(),
        ]);

        return [
            Stat::make(__('mes.widgets.openProductionOrders'), $data['open_orders'])
                ->description(__('mes.widgets.openProductionOrdersHint'))
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->descriptionColor('mes'),
            Stat::make(__('mes.widgets.runningOperations'), $data['running_operations'])
                ->description(__('mes.widgets.runningOperationsHint'))
                ->descriptionIcon('heroicon-o-cog-6-tooth')
                ->color('info')
                ->descriptionColor('mes'),
            Stat::make(__('mes.widgets.completedOrders'), $data['completed_orders'])
                ->description(__('mes.widgets.completedOrdersHint'))
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->descriptionColor('mes'),
            Stat::make(__('mes.widgets.openNonConformances'), $data['open_non_conformances'])
                ->description(__('mes.widgets.openNonConformancesHint'))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($data['open_non_conformances'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
