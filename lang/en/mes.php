<?php

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
    'boms' => [
      'plural' => 'Bills of materials',
      'singular' => 'Bill of materials',
    ],
    'downtimes' => [
      'plural' => 'Downtimes',
      'singular' => 'Downtime',
    ],
    'machine_devices' => [
      'plural' => 'Machine devices',
      'singular' => 'Machine device',
    ],
    'machine_incidents' => [
      'plural' => 'Machine incidents',
      'singular' => 'Machine incident',
    ],
    'machine_messages' => [
      'plural' => 'Machine messages',
      'singular' => 'Machine message',
    ],
    'machine_profiles' => [
      'plural' => 'Machine profiles',
      'singular' => 'Machine profile',
    ],
    'machine_sources' => [
      'plural' => 'Machine sources',
      'singular' => 'Machine source',
    ],
    'machine_unattributed_measurements' => [
      'plural' => 'Unattributed measurements',
      'singular' => 'Unattributed measurement',
    ],
    'non_conformances' => [
      'plural' => 'Non-conformances',
      'singular' => 'Non-conformance',
    ],
    'operation_process_summaries' => [
      'plural' => 'Operation process summaries',
      'singular' => 'Operation process summary',
    ],
    'operations' => [
      'plural' => 'Operations',
      'singular' => 'Operation',
    ],
    'production_orders' => [
      'plural' => 'Production orders',
      'singular' => 'Production order',
    ],
    'quality_checks' => [
      'plural' => 'Quality checks',
      'singular' => 'Quality check',
    ],
    'quality_plans' => [
      'plural' => 'Quality plans',
      'singular' => 'Quality plan',
    ],
    'routings' => [
      'plural' => 'Routings',
      'singular' => 'Routing',
    ],
    'shifts' => [
      'plural' => 'Shifts',
      'singular' => 'Shift',
    ],
    'unmapped_signals' => [
      'plural' => 'Unmapped signals',
      'singular' => 'Unmapped signal',
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
  'navigation' => [
    'machine_connectivity' => 'MES - Machine connectivity',
    'main' => 'MES',
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