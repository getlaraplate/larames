<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\SelfAuthenticatedApiRoute;
use Modules\Core\Support\CrudApiExposure;
use Modules\MES\Http\Controllers\MachineAgentConfigController;
use Modules\MES\Http\Controllers\MachineAgentConfigReportController;
use Modules\MES\Http\Controllers\MachineDataIngestController;
use Modules\MES\Http\Middleware\ResolveMachineSource;
use Modules\MES\Models\MachineAgentConfig;
use Modules\MES\Models\MachineAgentConfigReport;
use Modules\MES\Models\MachineMessage;

uses(RefreshDatabase::class);

/**
 * The route Laravel matches for a request, as the router would dispatch it.
 */
function machineMatchedRoute(string $method, string $uri): Illuminate\Routing\Route
{
    return Route::getRoutes()->match(Request::create($uri, $method));
}

it('serves the ingest on the CRUD path under the spec name', function (): void {
    expect(route('mes.api.machine-messages.insert', absolute: false))->toBe('/api/v1/insert/mes/machine-messages');
});

it('dispatches the ingest path to the ingest controller, not to the generic CRUD insert', function (): void {
    $route = machineMatchedRoute('POST', '/api/v1/insert/mes/machine-messages');

    expect($route->getName())->toBe('mes.api.machine-messages.insert')
        ->and($route->getActionName())->toBe(MachineDataIngestController::class)
        ->and(machineMatchedRoute('POST', '/api/v1/insert/mes/machine-sources')->getName())->toBe('core.api.insert');
});

it('serves the configuration routes on the CRUD paths under the spec names', function (): void {
    expect(route('mes.api.machine-agent-configs.detail', absolute: false))->toBe('/api/v1/detail/mes/machine-agent-configs')
        ->and(route('mes.api.machine-agent-config-reports.insert', absolute: false))->toBe('/api/v1/insert/mes/machine-agent-config-reports');
});

it('dispatches the configuration paths to their controllers, not to the generic CRUD detail and insert', function (): void {
    $detail = machineMatchedRoute('GET', '/api/v1/detail/mes/machine-agent-configs');
    $report = machineMatchedRoute('POST', '/api/v1/insert/mes/machine-agent-config-reports');

    expect($detail->getName())->toBe('mes.api.machine-agent-configs.detail')
        ->and($detail->getActionName())->toBe(MachineAgentConfigController::class)
        ->and($report->getName())->toBe('mes.api.machine-agent-config-reports.insert')
        ->and($report->getActionName())->toBe(MachineAgentConfigReportController::class)
        ->and(machineMatchedRoute('GET', '/api/v1/detail/mes/machine-sources')->getName())->toBe('core.api.detail');
});

it('resolves the source of the configuration routes by their permissions', function (): void {
    expect(Route::gatherRouteMiddleware(Route::getRoutes()->getByName('mes.api.machine-agent-configs.detail')))
        ->toContain(ResolveMachineSource::class . ':' . MachineAgentConfig::class . ',select')
        ->and(Route::gatherRouteMiddleware(Route::getRoutes()->getByName('mes.api.machine-agent-config-reports.insert')))
        ->toContain(ResolveMachineSource::class . ':' . MachineAgentConfigReport::class . ',insert');
});

it('authenticates through the API middleware and resolves the source by the ingest permission', function (): void {
    $middleware = Route::gatherRouteMiddleware(Route::getRoutes()->getByName('mes.api.machine-messages.insert'));

    expect($middleware)->toContain(ResolveMachineSource::class . ':' . MachineMessage::class . ',insert')
        ->and($middleware)->not->toContain(SelfAuthenticatedApiRoute::class);
});

it('counts the ingest limit in the cache store under the test runner, never in Redis', function (): void {
    $middleware = Route::gatherRouteMiddleware(Route::getRoutes()->getByName('mes.api.machine-messages.insert'));

    expect($middleware)->toContain(ThrottleRequests::class . ':mes-machine-ingest')
        ->and($middleware)->not->toContain(ThrottleRequestsWithRedis::class . ':mes-machine-ingest');
});

it('counts the configuration limit per service account in the cache store under the test runner', function (string $name): void {
    $middleware = Route::gatherRouteMiddleware(Route::getRoutes()->getByName($name));

    expect($middleware)->toContain(ThrottleRequests::class . ':mes-machine-agent-config')
        ->and($middleware)->not->toContain(ThrottleRequestsWithRedis::class . ':mes-machine-agent-config');
})->with(['mes.api.machine-agent-configs.detail', 'mes.api.machine-agent-config-reports.insert']);

it('no longer serves the old ingest path', function (): void {
    CrudApiExposure::enable();

    $this->postJson('/api/v1/mes/machine-data', [])->assertNotFound();

    expect(Route::has('mes.api.machine-data.ingest'))->toBeFalse();
});

it('refuses the machine routes with 403 while the API switch is off, like every api route', function (): void {
    $this->postJson('/api/v1/insert/mes/machine-messages', [])->assertForbidden();
    $this->postJson('/api/v1/insert/mes/machine-messages', [], ['Authorization' => 'Bearer 999|nonsense'])->assertForbidden();
    $this->getJson('/api/v1/detail/mes/machine-agent-configs')->assertForbidden();
    $this->postJson('/api/v1/insert/mes/machine-agent-config-reports', [])->assertForbidden();
    $this->getJson('/api/v1/select/core/users')->assertForbidden();
});
