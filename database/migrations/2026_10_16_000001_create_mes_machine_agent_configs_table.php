<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\MES\Enums\MESTables;

return new class extends Migration
{
    /**
     * Creates the configuration versions of the machine sources, then the foreign key of the source's active version,
     * whose column the sources create migration declares before this table exists.
     */
    public function up(): void
    {
        $table_name = MESTables::MachineAgentConfigs->value;
        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('company_id')->constrained(ERPTables::Companies->value, 'id', "{$table_name}_company_id_FK")->restrictOnDelete();
            $table->foreignId('source_id')
                ->constrained(MESTables::MachineSources->value, 'id', "{$table_name}_source_id_FK")
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('document');
            $table->foreignId('created_by')->nullable()->constrained(CoreTables::Users->value, 'id', "{$table_name}_created_by_FK")->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->unique(['source_id', 'version'], "{$table_name}_source_version_UN");
            MigrateUtils::prefixIndex($table, 'company_id');
        });

        $sources_table = MESTables::MachineSources->value;
        Schema::table($sources_table, function (Blueprint $table) use ($sources_table, $table_name): void {
            $table->foreign('active_config_id', "{$sources_table}_active_config_id_FK")
                ->references('id')
                ->on($table_name)
                ->nullOnDelete();
        });
    }

    /**
     * Drops the foreign key of the active version by name, and names its column too: SQLite drops a foreign key only by
     * its columns.
     */
    public function down(): void
    {
        $sources_table = MESTables::MachineSources->value;
        Schema::table($sources_table, function (Blueprint $table) use ($sources_table): void {
            $table->dropForeign("{$sources_table}_active_config_id_FK")->set('columns', ['active_config_id']);
        });

        Schema::dropIfExists(MESTables::MachineAgentConfigs->value);
    }
};
