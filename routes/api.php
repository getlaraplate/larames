<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\MES\Http\Controllers\MachineDataIngestController;
use Modules\MES\Http\Middleware\ResolveMachineSource;
use Modules\MES\Models\MachineMessage;

/*
 * Inbound endpoint of the machine sources (our edge agent or a customer gateway), on the CRUD path of the
 * messages it creates. Core's API authentication reads the bearer token of the source's service account; the
 * source is resolved from that account, so the URL carries no source id. Registered before Core's generic
 * `insert/{module}/{entity}`, which therefore never sees this path.
 */
Route::post('insert/mes/machine-messages', MachineDataIngestController::class)
    ->middleware([ResolveMachineSource::class . ':' . MachineMessage::class . ',insert', 'throttle:mes-machine-ingest'])
    ->name('machine-messages.insert');
