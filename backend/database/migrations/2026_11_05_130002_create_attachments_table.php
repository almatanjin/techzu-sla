<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users');
            $table->string('disk');
            $table->string('path')->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['ticket_id', 'ticket_message_id']);
            $table->index('ticket_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};

