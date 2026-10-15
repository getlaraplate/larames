<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Stornieren',
        'complete' => 'Abschließen',
        'completeOperation' => 'Arbeitsgang abschließen',
        'completeOrder' => 'Fertigungsauftrag abschließen',
        'release' => 'Freigeben',
        'skip' => 'Überspringen',
        'start' => 'Starten',
    ],
    'entities' => [
        'boms' => [
            'singular' => 'Stückliste',
            'plural' => 'Stücklisten',
        ],
        'downtimes' => [
            'singular' => 'Stillstand',
            'plural' => 'Stillstände',
        ],
        'machine_devices' => [
            'singular' => 'Maschinengerät',
            'plural' => 'Maschinengeräte',
        ],
        'machine_incidents' => [
            'singular' => 'Maschinenstörung',
            'plural' => 'Maschinenstörungen',
        ],
        'machine_messages' => [
            'singular' => 'Maschinennachricht',
            'plural' => 'Maschinennachrichten',
        ],
        'machine_profiles' => [
            'singular' => 'Maschinenprofil',
            'plural' => 'Maschinenprofile',
        ],
        'machine_sources' => [
            'singular' => 'Maschinenquelle',
            'plural' => 'Maschinenquellen',
        ],
        'machine_unattributed_measurements' => [
            'singular' => 'Nicht zugeordnete Messung',
            'plural' => 'Nicht zugeordnete Messungen',
        ],
        'non_conformances' => [
            'singular' => 'Abweichung',
            'plural' => 'Abweichungen',
        ],
        'operation_process_summaries' => [
            'singular' => 'Prozesszusammenfassung',
            'plural' => 'Prozesszusammenfassungen',
        ],
        'operations' => [
            'plural' => 'Arbeitsgänge',
            'singular' => 'Arbeitsgang',
        ],
        'production_orders' => [
            'plural' => 'Fertigungsaufträge',
            'singular' => 'Fertigungsauftrag',
        ],
        'quality_checks' => [
            'singular' => 'Qualitätsprüfung',
            'plural' => 'Qualitätsprüfungen',
        ],
        'quality_plans' => [
            'singular' => 'Prüfplan',
            'plural' => 'Prüfpläne',
        ],
        'routings' => [
            'singular' => 'Arbeitsplan',
            'plural' => 'Arbeitspläne',
        ],
        'shifts' => [
            'singular' => 'Schicht',
            'plural' => 'Schichten',
        ],
        'unmapped_signals' => [
            'singular' => 'Nicht zugeordnetes Signal',
            'plural' => 'Nicht zugeordnete Signale',
        ],
        'work_centers' => [
            'plural' => 'Arbeitsplätze',
            'singular' => 'Arbeitsplatz',
        ],
    ],
    'fields' => [
        'actual_minutes' => 'Ist-Minuten',
        'actual_start_at' => 'Ist-Start',
        'capacity_per_hour' => 'Kapazität/h',
        'cycle_time_minutes' => 'Zykluszeit (min]',
        'description' => 'Beschreibung',
        'efficiency' => 'Effizienz',
        'is_active' => 'Aktiv',
        'item' => 'Artikel',
        'lot_code' => 'Chargencode',
        'number' => 'Nummer',
        'planned_start_at' => 'Geplanter Start',
        'production_order' => 'Auftrag',
        'quantity_planned' => 'Geplante Menge',
        'quantity_produced' => 'Produzierte Menge',
        'sequence' => 'Reihenfolge',
        'setup_time_minutes' => 'Rüstzeit (min]',
        'status' => 'Status',
        'type' => 'Typ',
        'work_center' => 'Arbeitsplatz',
    ],
    'messages' => [
        'actionNotAllowed' => 'Aktion nicht erlaubt.',
        'confirmCancelOrder' => 'Diesen Fertigungsauftrag stornieren?',
        'confirmSkipOperation' => 'Diesen Arbeitsgang überspringen?',
        'operationCompleteFailed' => 'Der Arbeitsgang konnte nicht abgeschlossen werden.',
        'operationSkipped' => 'Arbeitsgang übersprungen.',
        'operationStarted' => 'Arbeitsgang gestartet.',
        'operationTransitionNotAllowed' => 'Statuswechsel für diesen Arbeitsgang nicht erlaubt.',
        'orderCancelled' => 'Auftrag storniert.',
        'orderCompleteFailed' => 'Der Auftrag konnte nicht abgeschlossen werden.',
        'orderReleased' => 'Auftrag freigegeben.',
        'orderTransitionNotAllowed' => 'Statuswechsel für diesen Auftrag nicht erlaubt.',
        'quantityMustBePositive' => 'Geben Sie eine Menge größer als null ein.',
    ],
    'navigation' => [
        'machine_connectivity' => 'MES - Maschinenanbindung',
        'main' => 'MES',
    ],
    'status' => [
        'operation' => [
            'completed' => 'Abgeschlossen',
            'in_progress' => 'In Bearbeitung',
            'planned' => 'Geplant',
            'ready' => 'Bereit',
            'skipped' => 'Übersprungen',
        ],
        'order' => [
            'cancelled' => 'Storniert',
            'completed' => 'Abgeschlossen',
            'draft' => 'Entwurf',
            'in_progress' => 'In Bearbeitung',
            'released' => 'Freigegeben',
        ],
    ],
    'types' => [
        'workCenter' => [
            'cell' => 'Zelle',
            'line' => 'Linie',
            'machine' => 'Maschine',
            'manual_station' => 'Handarbeitsplatz',
        ],
    ],
];
