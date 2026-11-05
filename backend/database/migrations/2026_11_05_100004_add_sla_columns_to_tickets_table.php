<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('tickets')->exists()) {
            throw new RuntimeException('Rebuild the database with php artisan migrate:fresh --seed');
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('sla_policy_minutes');
            $table->timestamp('sla_started_at');
            $table->timestamp('sla_warning_at');
            $table->timestamp('sla_due_at');

            $table->index(['status', 'sla_due_at']);
            $table->index(['status', 'sla_warning_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'sla_due_at']);
            $table->dropIndex(['status', 'sla_warning_at']);

            $table->dropColumn([
                'sla_policy_minutes',
                'sla_started_at',
                'sla_warning_at',
                'sla_due_at',
            ]);
        });
    }
};

