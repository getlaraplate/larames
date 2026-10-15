<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\MES\Filament\Resources\MachineSources\Pages\CreateMachineSource;
use Modules\MES\Filament\Resources\MachineSources\Pages\EditMachineSource;
use Modules\MES\Machine\MachineSourceResolver;
use Modules\MES\Models\MachineSource;
use Modules\MES\Tests\Support\MesTestHelpers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->company = MesTestHelpers::makeCompany();
});

/**
 * Writes `user_id` past the model's validation, as a forged or legacy row would hold it.
 */
function forgeSourceUser(MachineSource $source, int $user_id): void
{
    MachineSource::query()->withoutGlobalScopes()->toBase()->where('id', $source->id)->update(['user_id' => $user_id]);
}

function actAsMesSuperadmin(): void
{
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $superadmin */
    $superadmin = App\Models\User::query()->create(User::factory()->raw());
    $superadmin->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    test()->actingAs($superadmin);
    Filament::setCurrentPanel('admin');
}

it('resolves the source linked to a service account', function (): void {
    $account = User::factory()->serviceAccount()->create();
    $source = MachineSource::factory()->create(['user_id' => $account->id]);

    expect(resolve(MachineSourceResolver::class)->forUser($account)?->is($source))->toBeTrue()
        ->and($source->serviceAccount?->is($account))->toBeTrue();
});

it('resolves no source for a regular user, for nobody, for an account with no source or a deleted one', function (): void {
    $resolver = resolve(MachineSourceResolver::class);
    $regular = User::factory()->create();
    forgeSourceUser(MachineSource::factory()->create(), $regular->id);
    $unlinked = User::factory()->serviceAccount()->create();
    $deleted_owner = User::factory()->serviceAccount()->create();
    MachineSource::factory()->create(['user_id' => $deleted_owner->id])->delete();

    expect($resolver->forUser($regular))->toBeNull()
        ->and($resolver->forUser(null))->toBeNull()
        ->and($resolver->forUser($unlinked))->toBeNull()
        ->and($resolver->forUser($deleted_owner))->toBeNull();
});

it('links one source at most to a service account', function (): void {
    $account = User::factory()->serviceAccount()->create();
    MachineSource::factory()->create(['user_id' => $account->id]);

    expect(fn () => MachineSource::factory()->create(['user_id' => $account->id]))->toThrow(ValidationException::class);
    expect(fn () => forgeSourceUser(MachineSource::factory()->create(), $account->id))->toThrow(UniqueConstraintViolationException::class);
});

it('refuses to link a user that is not a service account', function (): void {
    $regular = User::factory()->create();

    expect(fn () => MachineSource::factory()->create(['user_id' => $regular->id]))->toThrow(ValidationException::class);
});

it('offers only service accounts in the source form and refuses any other user', function (): void {
    actAsMesSuperadmin();
    $account = User::factory()->serviceAccount()->create();
    $regular = User::factory()->create();
    $fields = ['company_id' => $this->company->id, 'code' => 'gw-1', 'name' => 'Gateway 1', 'normalizer' => 'canonical', 'transport' => 'http', 'heartbeat_timeout_seconds' => 120, 'is_active' => true];

    Livewire::test(CreateMachineSource::class)
        ->assertFormFieldExists('user_id', static fn (Select $field): bool => [$account->id] === array_keys($field->getOptions()))
        ->fillForm([...$fields, 'user_id' => $regular->id])
        ->call('create')
        ->assertHasFormErrors(['user_id']);

    expect(MachineSource::query()->where('code', 'gw-1')->exists())->toBeFalse();

    Livewire::test(CreateMachineSource::class)
        ->fillForm([...$fields, 'user_id' => $account->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MachineSource::query()->where('code', 'gw-1')->value('user_id'))->toBe($account->id);
});

it('has no token actions on the source page and no tokens of its own', function (): void {
    actAsMesSuperadmin();
    $source = MachineSource::factory()->create();

    Livewire::test(EditMachineSource::class, ['record' => $source->getKey()])
        ->assertActionDoesNotExist('issue_token')
        ->assertActionDoesNotExist('revoke_token')
        ->assertActionExists('reprocess_range');

    expect(method_exists(MachineSource::class, 'tokens'))->toBeFalse()
        ->and(method_exists(MachineSource::class, 'createToken'))->toBeFalse();
});
