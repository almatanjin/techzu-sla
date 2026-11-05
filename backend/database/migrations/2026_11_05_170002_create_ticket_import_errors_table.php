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
        Schema::create('ticket_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_import_id')->constrained('ticket_imports')->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->string('external_reference', 100)->nullable();
            $table->string('kind', 32);
            $table->json('reasons');
            $table->json('raw_row')->nullable();
            $table->timestamps();

            $table->unique(['ticket_import_id', 'line']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_import_errors');
    }
};

