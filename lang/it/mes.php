<?php

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
      'plural' => 'Distinte base',
      'singular' => 'Distinta base',
    ],
    'downtimes' => [
      'plural' => 'Fermi macchina',
      'singular' => 'Fermo macchina',
    ],
    'machine_devices' => [
      'plural' => 'Dispositivi macchina',
      'singular' => 'Dispositivo macchina',
    ],
    'machine_incidents' => [
      'plural' => 'Anomalie macchina',
      'singular' => 'Anomalia macchina',
    ],
    'machine_messages' => [
      'plural' => 'Messaggi macchina',
      'singular' => 'Messaggio macchina',
    ],
    'machine_profiles' => [
      'plural' => 'Profili macchina',
      'singular' => 'Profilo macchina',
    ],
    'machine_sources' => [
      'plural' => 'Sorgenti macchina',
      'singular' => 'Sorgente macchina',
    ],
    'machine_unattributed_measurements' => [
      'plural' => 'Misure non attribuite',
      'singular' => 'Misura non attribuita',
    ],
    'non_conformances' => [
      'plural' => 'Non conformità',
      'singular' => 'Non conformità',
    ],
    'operation_process_summaries' => [
      'plural' => 'Riepiloghi di processo',
      'singular' => 'Riepilogo di processo',
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
      'plural' => 'Controlli qualità',
      'singular' => 'Controllo qualità',
    ],
    'quality_plans' => [
      'plural' => 'Piani di controllo',
      'singular' => 'Piano di controllo',
    ],
    'routings' => [
      'plural' => 'Cicli di lavoro',
      'singular' => 'Ciclo di lavoro',
    ],
    'shifts' => [
      'plural' => 'Turni',
      'singular' => 'Turno',
    ],
    'unmapped_signals' => [
      'plural' => 'Segnali non mappati',
      'singular' => 'Segnale non mappato',
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