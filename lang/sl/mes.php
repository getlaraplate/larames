<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Storniraj',
        'complete' => 'Zaključi',
        'completeOperation' => 'Zaključi operacijo',
        'completeOrder' => 'Zaključi delovni nalog',
        'release' => 'Sprosti',
        'skip' => 'Preskoči',
        'start' => 'Začni',
    ],
    'entities' => [
        'operations' => [
            'plural' => 'Operacije',
            'singular' => 'Operacija',
        ],
        'production_orders' => [
            'plural' => 'Delovni nalogi',
            'singular' => 'Delovni nalog',
        ],
        'work_centers' => [
            'plural' => 'Delovna mesta',
            'singular' => 'Delovno mesto',
        ],
    ],
    'fields' => [
        'actual_minutes' => 'Dejanske minute',
        'actual_start_at' => 'Dejanski začetek',
        'capacity_per_hour' => 'Zmogljivost/h',
        'cycle_time_minutes' => 'Cikel (min]',
        'description' => 'Opis',
        'efficiency' => 'Učinkovitost',
        'is_active' => 'Aktivno',
        'item' => 'Artikel',
        'lot_code' => 'Koda lota',
        'number' => 'Številka',
        'planned_start_at' => 'Načrtovani začetek',
        'production_order' => 'Nalog',
        'quantity_planned' => 'Načrtovana količina',
        'quantity_produced' => 'Proizvedena količina',
        'sequence' => 'Zaporedje',
        'setup_time_minutes' => 'Priprava (min]',
        'status' => 'Stanje',
        'type' => 'Vrsta',
        'work_center' => 'Delovno mesto',
    ],
    'messages' => [
        'actionNotAllowed' => 'Dejanje ni dovoljeno.',
        'confirmCancelOrder' => 'Želite stornirati ta delovni nalog?',
        'confirmSkipOperation' => 'Želite preskočiti to operacijo?',
        'operationCompleteFailed' => 'Operacije ni bilo mogoče zaključiti.',
        'operationSkipped' => 'Operacija preskočena.',
        'operationStarted' => 'Operacija začeta.',
        'operationTransitionNotAllowed' => 'Prehod za to operacijo ni dovoljen.',
        'orderCancelled' => 'Nalog storniran.',
        'orderCompleteFailed' => 'Naloga ni bilo mogoče zaključiti.',
        'orderReleased' => 'Nalog sproščen.',
        'orderTransitionNotAllowed' => 'Prehod za ta nalog ni dovoljen.',
        'quantityMustBePositive' => 'Vnesite količino, večjo od nič.',
    ],
    'status' => [
        'operation' => [
            'completed' => 'Zaključena',
            'in_progress' => 'V teku',
            'planned' => 'Načrtovana',
            'ready' => 'Pripravljena',
            'skipped' => 'Preskočena',
        ],
        'order' => [
            'cancelled' => 'Storniran',
            'completed' => 'Zaključen',
            'draft' => 'Osnutek',
            'in_progress' => 'V teku',
            'released' => 'Sproščen',
        ],
    ],
    'types' => [
        'workCenter' => [
            'cell' => 'Celica',
            'line' => 'Linija',
            'machine' => 'Stroj',
            'manual_station' => 'Ročno delovno mesto',
        ],
    ],
];
