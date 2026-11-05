<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_warning_notified_at')->nullable()->after('sla_due_at');
            $table->timestamp('sla_breached_at')->nullable()->after('sla_warning_notified_at');
            $table->timestamp('escalated_at')->nullable()->after('sla_breached_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'sla_warning_notified_at',
                'sla_breached_at',
                'escalated_at',
            ]);
        });
    }
};

