<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Cancel',
        'complete' => 'Complete',
        'completeOperation' => 'Complete operation',
        'completeOrder' => 'Complete production order',
        'release' => 'Release',
        'skip' => 'Skip',
        'start' => 'Start',
    ],
    'entities' => [
        'operations' => [
            'plural' => 'Operations',
            'singular' => 'Operation',
        ],
        'production_orders' => [
            'plural' => 'Production orders',
            'singular' => 'Production order',
        ],
        'work_centers' => [
            'plural' => 'Work centers',
            'singular' => 'Work center',
        ],
    ],
    'fields' => [
        'actual_minutes' => 'Actual minutes',
        'actual_start_at' => 'Actual start',
        'capacity_per_hour' => 'Capacity/h',
        'cycle_time_minutes' => 'Cycle (min]',
        'description' => 'Description',
        'efficiency' => 'Efficiency',
        'is_active' => 'Active',
        'item' => 'Item',
        'lot_code' => 'Lot code',
        'number' => 'Number',
        'planned_start_at' => 'Planned start',
        'production_order' => 'Order',
        'quantity_planned' => 'Planned quantity',
        'quantity_produced' => 'Produced quantity',
        'sequence' => 'Sequence',
        'setup_time_minutes' => 'Setup (min]',
        'status' => 'Status',
        'type' => 'Type',
        'work_center' => 'Work center',
    ],
    'messages' => [
        'actionNotAllowed' => 'Action not allowed.',
        'confirmCancelOrder' => 'Cancel this production order?',
        'confirmSkipOperation' => 'Skip this operation?',
        'operationCompleteFailed' => 'Could not complete the operation.',
        'operationSkipped' => 'Operation skipped.',
        'operationStarted' => 'Operation started.',
        'operationTransitionNotAllowed' => 'Transition not allowed for this operation.',
        'orderCancelled' => 'Order cancelled.',
        'orderCompleteFailed' => 'Could not complete the order.',
        'orderReleased' => 'Order released.',
        'orderTransitionNotAllowed' => 'Transition not allowed for this order.',
        'quantityMustBePositive' => 'Enter a quantity greater than zero.',
    ],
    'status' => [
        'operation' => [
            'completed' => 'Completed',
            'in_progress' => 'In progress',
            'planned' => 'Planned',
            'ready' => 'Ready',
            'skipped' => 'Skipped',
        ],
        'order' => [
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
            'draft' => 'Draft',
            'in_progress' => 'In progress',
            'released' => 'Released',
        ],
    ],
    'types' => [
        'workCenter' => [
            'cell' => 'Cell',
            'line' => 'Line',
            'machine' => 'Machine',
            'manual_station' => 'Manual station',
        ],
    ],
];
