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
        'boms' => [
            'singular' => 'Distinta base',
            'plural' => 'Distinte base',
        ],
        'downtimes' => [
            'singular' => 'Fermo macchina',
            'plural' => 'Fermi macchina',
        ],
        'machine_devices' => [
            'singular' => 'Dispositivo macchina',
            'plural' => 'Dispositivi macchina',
        ],
        'machine_incidents' => [
            'singular' => 'Anomalia macchina',
            'plural' => 'Anomalie macchina',
        ],
        'machine_messages' => [
            'singular' => 'Messaggio macchina',
            'plural' => 'Messaggi macchina',
        ],
        'machine_profiles' => [
            'singular' => 'Profilo macchina',
            'plural' => 'Profili macchina',
        ],
        'machine_sources' => [
            'singular' => 'Sorgente macchina',
            'plural' => 'Sorgenti macchina',
        ],
        'machine_unattributed_measurements' => [
            'singular' => 'Misura non attribuita',
            'plural' => 'Misure non attribuite',
        ],
        'non_conformances' => [
            'singular' => 'Non conformità',
            'plural' => 'Non conformità',
        ],
        'operation_process_summaries' => [
            'singular' => 'Riepilogo di processo',
            'plural' => 'Riepiloghi di processo',
        ],
        'operations' => [
            'plural' => 'Lavorazioni',
            'singular' => 'Lavorazione',
        ],
        'production_orders' => [
            'plural' => 'Ordini di produzione',
            'singular' => 'Ordine di produzione',
        ],
        'quality_checks' => [
            'singular' => 'Controllo qualità',
            'plural' => 'Controlli qualità',
        ],
        'quality_plans' => [
            'singular' => 'Piano di controllo',
            'plural' => 'Piani di controllo',
        ],
        'routings' => [
            'singular' => 'Ciclo di lavoro',
            'plural' => 'Cicli di lavoro',
        ],
        'shifts' => [
            'singular' => 'Turno',
            'plural' => 'Turni',
        ],
        'unmapped_signals' => [
            'singular' => 'Segnale non mappato',
            'plural' => 'Segnali non mappati',
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
    'navigation' => [
        'machine_connectivity' => 'MES - Connettività macchine',
        'main' => 'MES',
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
