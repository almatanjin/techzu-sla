<?php

namespace Database\Factories;

use App\Enums\TicketImportStatus;
use App\Models\TicketImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketImport>
 */
class TicketImportFactory extends Factory
{
    protected $model = TicketImport::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'original_filename' => 'tickets.csv',
            'disk' => 'local',
            'path' => 'imports/test.csv',
            'status' => TicketImportStatus::Pending,
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
        ];
    }
}

