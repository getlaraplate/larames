<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\MES\Enums\MachineTransport;
use Modules\MES\Enums\MESTables;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = MESTables::MachineSources->value;
        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('company_id')->constrained(ERPTables::Companies->value, 'id', "{$table_name}_company_id_FK")->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(CoreTables::Users->value, 'id', "{$table_name}_user_id_FK")->nullOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('normalizer', 64)->default('canonical');
            $table->enum('transport', MachineTransport::values())->default(MachineTransport::Http->value);
            $table->string('mqtt_topic', 255)->nullable();
            $table->json('normalizer_options')->nullable();
            $table->string('protocol_version', 16)->default('1');
            $table->unsignedInteger('heartbeat_timeout_seconds')->default(120);
            $table->dateTime('last_seen_at')->nullable();
            $table->unsignedBigInteger('last_seq')->nullable();
            $table->boolean('is_active')->default(true);
            // The foreign key to the configurations is declared by their create migration, which runs after this one.
            $table->unsignedBigInteger('active_config_id')->nullable()->index("{$table_name}_active_config_id_IDX");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->unique(['company_id', 'code'], "{$table_name}_company_code_UN");
            $table->unique('user_id', "{$table_name}_user_UN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MESTables::MachineSources->value);
    }
};
