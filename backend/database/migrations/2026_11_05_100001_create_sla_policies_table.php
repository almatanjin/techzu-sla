<?php

use App\Enums\TicketPriority;
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
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('priority', 16)->unique();
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedTinyInteger('warning_threshold_percent');
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['priority' => TicketPriority::Low->value, 'resolution_minutes' => 2880, 'warning_threshold_percent' => 75],
            ['priority' => TicketPriority::Medium->value, 'resolution_minutes' => 1440, 'warning_threshold_percent' => 75],
            ['priority' => TicketPriority::High->value, 'resolution_minutes' => 480, 'warning_threshold_percent' => 75],
            ['priority' => TicketPriority::Critical->value, 'resolution_minutes' => 120, 'warning_threshold_percent' => 75],
        ];

        foreach ($defaults as $default) {
            DB::table('sla_policies')->insert([
                ...$default,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
