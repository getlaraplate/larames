<?php

declare(strict_types=1);

namespace Modules\MES\Database\Seeders;

use Modules\Core\Authorization\PermissionManifest;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Database\Seeders\PermissionRefreshSeeder;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Seeding\Contracts\DeclaresSeedDependencies;
use Modules\Core\Seeding\SeedReconciler;
use Modules\Core\Support\PermissionName;
use Modules\MES\Models\MachineMessage;
use Override;
use Spatie\Permission\PermissionRegistrar;

class MESDatabaseSeeder extends Seeder implements DeclaresSeedDependencies
{
    /**
     * The `api` role administrators give to the service accounts of the machine sources.
     */
    public const string MACHINE_AGENT_ROLE = 'mes-machine-agent';

    /**
     * `machineAgentRole()` grants `api` permissions that only exist once `permission:refresh` has run, so this node
     * follows {@see PermissionRefreshSeeder}, declared rather than left to the edges `module.json` implies.
     *
     * @return list<class-string>
     */
    #[Override]
    public static function dependsOn(): array
    {
        return [PermissionRefreshSeeder::class];
    }

    /**
     * Every row carries the same column set (`encrypted`, `choices` included even when null):
     * {@see SeedReconciler} batches these rows into a single `upsert()`, which requires a uniform
     * column set across the whole batch — a mismatch surfaces as a binding-count SQL error.
     *
     * @return array<int, array{name: string, value: mixed, encrypted: bool, choices: ?array<int, mixed>, type: SettingTypeEnum, group_name: string, description: string}>
     */
    public static function runtimeSettingDefinitions(): array
    {
        return [
            [
                'name' => 'lots.number_format',
                'value' => '{YEAR}{MONTH}{DAY}-{SEQ}',
                'encrypted' => false,
                'choices' => null,
                'type' => SettingTypeEnum::String,
                'group_name' => 'mes',
                'description' => 'Lot number generation format',
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outcome = app(SeedReconciler::class)->reconcile(
            self::internalSettingsDefinition('MES', self::runtimeSettingDefinitions()),
        );

        $this->command?->line(
            '    - created ' . count($outcome->created) . ', realigned ' . count($outcome->realigned) . ", unchanged {$outcome->unchanged}",
        );

        $this->ensureDomainPermissions();
        $this->machineAgentRole();
    }

    /**
     * Materializes this module's slice of the permission manifest.
     *
     * `permission:refresh` creates the same names from the same declaration and
     * runs before every module seeder in the graph; this keeps a module seeded in
     * isolation (as the test suites do) self-sufficient.
     */
    private function ensureDomainPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = new Permission;

        foreach (app(PermissionManifest::class)->namesFor('MES') as $name) {
            $permission->newQuery()->firstOrCreate(['name' => $name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->line('    - MES domain permissions <fg=green>updated</>');
    }

    /**
     * Creates the `api` role of the machine agents and grants it the `api` permissions of the machine routes. The
     * permissions come from `permission:refresh`, which runs first ({@see self::dependsOn()}); one that does not exist
     * (the seeder run on its own, outside the graph) is skipped, and the next seed after a refresh grants it. Granted without detaching on every run, so a role an
     * operator widened keeps its grants.
     */
    private function machineAgentRole(): void
    {
        $this->logOperation(Role::class);

        /** @var class-string<Role> $role_class */
        $role_class = config('permission.models.role');

        /** @var class-string<Permission> $permission_class */
        $permission_class = config('permission.models.permission');

        $guard = CoreDatabaseSeeder::API_GUARD;
        $role = $role_class::query()->where(['name' => self::MACHINE_AGENT_ROLE, 'guard_name' => $guard])->first();

        if ($role === null) {
            $role = $this->create($role_class, [
                'name' => self::MACHINE_AGENT_ROLE,
                'guard_name' => $guard,
                'description' => 'Machine agents: the service accounts of the machine sources.',
            ]);
        }

        foreach ([PermissionName::forClass(MachineMessage::class, ActionEnum::Insert->value)] as $permission_name) {
            $permission_id = $permission_class::query()->where(['name' => $permission_name, 'guard_name' => $guard])->value('id');

            if ($permission_id !== null) {
                $role->permissions()->syncWithoutDetaching([$permission_id]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
