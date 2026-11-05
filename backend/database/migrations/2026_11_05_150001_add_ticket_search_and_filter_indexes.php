<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = 0');

        Schema::table('tickets', function (Blueprint $table) {
            $table->fullText(['subject', 'description'], 'tickets_fulltext');
            $table->index(['customer_id', 'status', 'created_at'], 'tickets_customer_id_status_created_at_index');
            $table->index(['category_id', 'status'], 'tickets_category_id_status_index');
            $table->index(['status', 'priority'], 'tickets_status_priority_index');
            $table->index('created_at', 'tickets_created_at_index');

            $table->dropIndex('tickets_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('category_id', 'tickets_category_id_foreign');
            $table->index('customer_id', 'tickets_customer_id_foreign');
            $table->index('status', 'tickets_status_index');

            $table->dropIndex('tickets_fulltext');
            $table->dropIndex('tickets_customer_id_status_created_at_index');
            $table->dropIndex('tickets_category_id_status_index');
            $table->dropIndex('tickets_status_priority_index');
            $table->dropIndex('tickets_created_at_index');
        });
    }
};
