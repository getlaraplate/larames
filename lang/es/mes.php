<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Anular',
        'complete' => 'Completar',
        'completeOperation' => 'Completar operación',
        'completeOrder' => 'Completar orden de producción',
        'release' => 'Liberar',
        'skip' => 'Omitir',
        'start' => 'Iniciar',
    ],
    'entities' => [
        'operations' => [
            'plural' => 'Operaciones',
            'singular' => 'Operación',
        ],
        'production_orders' => [
            'plural' => 'Órdenes de producción',
            'singular' => 'Orden de producción',
        ],
        'work_centers' => [
            'plural' => 'Puestos de trabajo',
            'singular' => 'Puesto de trabajo',
        ],
    ],
    'fields' => [
        'actual_minutes' => 'Minutos reales',
        'actual_start_at' => 'Inicio real',
        'capacity_per_hour' => 'Capacidad/h',
        'cycle_time_minutes' => 'Ciclo (min]',
        'description' => 'Descripción',
        'efficiency' => 'Eficiencia',
        'is_active' => 'Activo',
        'item' => 'Artículo',
        'lot_code' => 'Código de lote',
        'number' => 'Número',
        'planned_start_at' => 'Inicio previsto',
        'production_order' => 'Orden',
        'quantity_planned' => 'Cantidad prevista',
        'quantity_produced' => 'Cantidad producida',
        'sequence' => 'Secuencia',
        'setup_time_minutes' => 'Preparación (min]',
        'status' => 'Estado',
        'type' => 'Tipo',
        'work_center' => 'Puesto de trabajo',
    ],
    'messages' => [
        'actionNotAllowed' => 'Acción no permitida.',
        'confirmCancelOrder' => '¿Anular esta orden de producción?',
        'confirmSkipOperation' => '¿Omitir esta operación?',
        'operationCompleteFailed' => 'No se pudo completar la operación.',
        'operationSkipped' => 'Operación omitida.',
        'operationStarted' => 'Operación iniciada.',
        'operationTransitionNotAllowed' => 'Transición no permitida para esta operación.',
        'orderCancelled' => 'Orden anulada.',
        'orderCompleteFailed' => 'No se pudo completar la orden.',
        'orderReleased' => 'Orden liberada.',
        'orderTransitionNotAllowed' => 'Transición no permitida para esta orden.',
        'quantityMustBePositive' => 'Introduce una cantidad mayor que cero.',
    ],
    'status' => [
        'operation' => [
            'completed' => 'Completada',
            'in_progress' => 'En curso',
            'planned' => 'Planificada',
            'ready' => 'Lista',
            'skipped' => 'Omitida',
        ],
        'order' => [
            'cancelled' => 'Anulada',
            'completed' => 'Completada',
            'draft' => 'Borrador',
            'in_progress' => 'En curso',
            'released' => 'Liberada',
        ],
    ],
    'types' => [
        'workCenter' => [
            'cell' => 'Célula',
            'line' => 'Línea',
            'machine' => 'Máquina',
            'manual_station' => 'Puesto manual',
        ],
    ],
];
