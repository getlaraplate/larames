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
        'operations' => [
            'plural' => 'Arbeitsgänge',
            'singular' => 'Arbeitsgang',
        ],
        'production_orders' => [
            'plural' => 'Fertigungsaufträge',
            'singular' => 'Fertigungsauftrag',
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
