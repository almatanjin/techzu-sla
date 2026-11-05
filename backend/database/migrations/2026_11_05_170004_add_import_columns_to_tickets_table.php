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
            $table->string('external_reference', 100)->nullable()->unique()->after('ticket_number');
            $table->foreignId('ticket_import_id')->nullable()->after('external_reference')->constrained('ticket_imports')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['ticket_import_id']);
            $table->dropColumn(['ticket_import_id', 'external_reference']);
        });
    }
};

