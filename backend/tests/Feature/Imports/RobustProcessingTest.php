<?php

use App\Enums\TicketImportStatus;
use App\Imports\ImportRowValidator;
use App\Jobs\ProcessTicketImportChunk;
use App\Jobs\ScanTicketImport;
use App\Models\Ticket;
use App\Models\TicketImport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CategorySeeder::class);
    $this->seed(DemoUserSeeder::class);

    $this->admin = User::query()->where('email', 'admin@techzu-sla.test')->firstOrFail();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;
});

it('creates tickets once and increments counts once when running ProcessTicketImportChunk twice', function () {
    $file = importFixtureUpload('valid-3.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $import = TicketImport::findOrFail($importId);
    expect($import->status)->toBe(TicketImportStatus::Completed)
        ->and($import->processed_rows)->toBe(3)
        ->and($import->imported_rows)->toBe(3)
        ->and(Ticket::query()->where('ticket_import_id', $importId)->count())->toBe(3);

    // Run chunk 0 again
    $chunkJob = new ProcessTicketImportChunk(
        importId: $importId,
        chunkIndex: 0,
        offset: 0,
        firstLine: 2,
        rowCount: 3,
    );

    app()->call([$chunkJob, 'handle']);

    $import->refresh();
    expect($import->processed_rows)->toBe(3)
        ->and($import->imported_rows)->toBe(3)
        ->and(Ticket::query()->where('ticket_import_id', $importId)->count())->toBe(3);
});

it('marks import failed with Some rows could not be processed. when chunk 2 fails, keeping remaining chunks', function () {
    // Custom validator that throws an exception during chunk 2 (chunk index 1: rows 501-1000)
    $failingValidator = new class extends ImportRowValidator {
        public function validate(
            int $line,
            array $mapped,
            array $rawRow,
            array $customersByEmail,
            array $categoriesByName,
            CarbonImmutable $now,
        ): array {
            if ($line > 501 && $line <= 1001) {
                throw new \RuntimeException('Simulated permanent failure in chunk 2.');
            }

            return parent::validate($line, $mapped, $rawRow, $customersByEmail, $categoriesByName, $now);
        }
    };

    $this->app->instance(ImportRowValidator::class, $failingValidator);

    $file = importFixtureUpload('valid-2000.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $import = TicketImport::findOrFail($importId);

    expect($import->status)->toBe(TicketImportStatus::Failed)
        ->and($import->failure_message)->toBe('Some rows could not be processed.')
        ->and($import->total_rows)->toBe(2000)
        ->and($import->processed_rows)->toBe(1500)
        ->and($import->imported_rows)->toBe(1500)
        ->and(Ticket::query()->where('ticket_import_id', $importId)->count())->toBe(1500);
});

it('marks import failed with The file could not be read. when file is unreadable', function () {
    $import = TicketImport::factory()->create([
        'user_id' => $this->admin->id,
        'original_filename' => 'nonexistent.csv',
        'disk' => 'local',
        'path' => 'imports/nonexistent.csv',
        'status' => TicketImportStatus::Pending,
    ]);

    // Ensure file does not exist
    Storage::disk('local')->delete('imports/nonexistent.csv');

    (new ScanTicketImport($import->id))->handle();

    $import->refresh();

    expect($import->status)->toBe(TicketImportStatus::Failed)
        ->and($import->failure_message)->toBe('The file could not be read.');
});

it('accepts a new upload after a failed import', function () {
    TicketImport::factory()->create([
        'user_id' => $this->admin->id,
        'status' => TicketImportStatus::Failed,
        'failure_message' => 'Some past failure',
    ]);

    $file = importFixtureUpload('valid-3.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202)
        ->assertJson([
            'status' => 'pending',
        ]);
});

