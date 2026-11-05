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
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->boolean('is_working_day');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['day_of_week' => 0, 'is_working_day' => true, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00'],
            ['day_of_week' => 1, 'is_working_day' => true, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00'],
            ['day_of_week' => 2, 'is_working_day' => true, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00'],
            ['day_of_week' => 3, 'is_working_day' => true, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00'],
            ['day_of_week' => 4, 'is_working_day' => true, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00'],
            ['day_of_week' => 5, 'is_working_day' => false, 'opens_at' => null, 'closes_at' => null],
            ['day_of_week' => 6, 'is_working_day' => false, 'opens_at' => null, 'closes_at' => null],
        ];

        foreach ($defaults as $default) {
            DB::table('business_hours')->insert([
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
        Schema::dropIfExists('business_hours');
    }
};

