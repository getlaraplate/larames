<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\SelfAuthenticatedApiRoute;
use Modules\Core\Support\CrudApiExposure;
use Modules\MES\Http\Controllers\MachineDataIngestController;
use Modules\MES\Http\Middleware\ResolveMachineSource;
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

it('authenticates through the API middleware and resolves the source by the ingest permission', function (): void {
    $middleware = Route::gatherRouteMiddleware(Route::getRoutes()->getByName('mes.api.machine-messages.insert'));

    expect($middleware)->toContain(ResolveMachineSource::class . ':' . MachineMessage::class . ',insert')
        ->and($middleware)->not->toContain(SelfAuthenticatedApiRoute::class);
});

it('no longer serves the old ingest path', function (): void {
    CrudApiExposure::enable();

    $this->postJson('/api/v1/mes/machine-data', [])->assertNotFound();

    expect(Route::has('mes.api.machine-data.ingest'))->toBeFalse();
});

it('refuses the ingest with 403 while the API switch is off, like every api route', function (): void {
    $this->postJson('/api/v1/insert/mes/machine-messages', [])->assertForbidden();
    $this->postJson('/api/v1/insert/mes/machine-messages', [], ['Authorization' => 'Bearer 999|nonsense'])->assertForbidden();
    $this->getJson('/api/v1/select/core/users')->assertForbidden();
});
