<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\MES\Enums\MachineAgentConfigOutcome;
use Modules\MES\Enums\MESTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = MESTables::MachineAgentConfigReports->value;
        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('company_id')->constrained(ERPTables::Companies->value, 'id', "{$table_name}_company_id_FK")->restrictOnDelete();
            $table->foreignId('source_id')
                ->constrained(MESTables::MachineSources->value, 'id', "{$table_name}_source_id_FK")
                ->cascadeOnDelete();
            $table->unsignedInteger('config_version');
            $table->enum('outcome', MachineAgentConfigOutcome::values());
            $table->json('errors')->nullable();
            $table->string('agent_version', 64);
            $table->dateTime('reported_at');

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->index(['source_id', 'reported_at'], "{$table_name}_source_reported_IDX");
            MigrateUtils::prefixIndex($table, 'company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MESTables::MachineAgentConfigReports->value);
    }
};
