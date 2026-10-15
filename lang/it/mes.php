<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Annulla',
        'complete' => 'Completa',
        'completeOperation' => 'Completa lavorazione',
        'completeOrder' => 'Completa ordine di produzione',
        'release' => 'Rilascia',
        'skip' => 'Salta',
        'start' => 'Avvia',
    ],
    'entities' => [
        'operations' => [
            'plural' => 'Lavorazioni',
            'singular' => 'Lavorazione',
        ],
        'production_orders' => [
            'plural' => 'Ordini di produzione',
            'singular' => 'Ordine di produzione',
        ],
        'work_centers' => [
            'plural' => 'Postazioni',
            'singular' => 'Postazione',
        ],
    ],
    'fields' => [
        'actual_minutes' => 'Minuti effettivi',
        'actual_start_at' => 'Inizio effettivo',
        'capacity_per_hour' => 'Capacità/h',
        'cycle_time_minutes' => 'Ciclo (min]',
        'description' => 'Descrizione',
        'efficiency' => 'Efficienza',
        'is_active' => 'Attivo',
        'item' => 'Articolo',
        'lot_code' => 'Codice lotto',
        'number' => 'Numero',
        'planned_start_at' => 'Inizio pianificato',
        'production_order' => 'Ordine',
        'quantity_planned' => 'Quantità pianificata',
        'quantity_produced' => 'Quantità prodotta',
        'sequence' => 'Sequenza',
        'setup_time_minutes' => 'Setup (min]',
        'status' => 'Stato',
        'type' => 'Tipo',
        'work_center' => 'Postazione',
    ],
    'messages' => [
        'actionNotAllowed' => 'Azione non consentita.',
        'confirmCancelOrder' => 'Annullare questo ordine di produzione?',
        'confirmSkipOperation' => 'Saltare questa lavorazione?',
        'operationCompleteFailed' => 'Impossibile completare la lavorazione.',
        'operationSkipped' => 'Lavorazione saltata.',
        'operationStarted' => 'Lavorazione avviata.',
        'operationTransitionNotAllowed' => 'Transizione non consentita per questa lavorazione.',
        'orderCancelled' => 'Ordine annullato.',
        'orderCompleteFailed' => 'Impossibile completare l’ordine.',
        'orderReleased' => 'Ordine rilasciato.',
        'orderTransitionNotAllowed' => 'Transizione non consentita per questo ordine.',
        'quantityMustBePositive' => 'Inserisci una quantità maggiore di zero.',
    ],
    'status' => [
        'operation' => [
            'completed' => 'Completata',
            'in_progress' => 'In corso',
            'planned' => 'Pianificata',
            'ready' => 'Pronta',
            'skipped' => 'Saltata',
        ],
        'order' => [
            'cancelled' => 'Annullato',
            'completed' => 'Completato',
            'draft' => 'Bozza',
            'in_progress' => 'In corso',
            'released' => 'Rilasciato',
        ],
    ],
    'types' => [
        'workCenter' => [
            'cell' => 'Cella',
            'line' => 'Linea',
            'machine' => 'Macchina',
            'manual_station' => 'Postazione manuale',
        ],
    ],
];
