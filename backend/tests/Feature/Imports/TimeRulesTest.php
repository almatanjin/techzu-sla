<?php

use App\Models\Ticket;
use App\Models\TicketImportError;
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

it('enforces row time rules accurately', function () {
    $now = CarbonImmutable::parse('2026-11-01 12:00:00');
    CarbonImmutable::setTestNow($now);

    $file = importFixtureUpload('time-rules.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 8,
            'processed_rows' => 8,
            'imported_rows' => 3,
            'failed_rows' => 5,
            'duplicate_rows' => 0,
        ]);

    // 1. 2026-10-01 09:00 without offset is stored as 03:00 UTC
    $dhakaTicket = Ticket::query()->where('external_reference', 'TIME-DHAKA')->firstOrFail();
    expect($dhakaTicket->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 03:00:00');

    // 2. ISO 8601 with +00:00 is kept as given (09:00 UTC)
    $isoTicket = Ticket::query()->where('external_reference', 'TIME-ISO')->firstOrFail();
    expect($isoTicket->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:00:00');

    // 3. Empty created_at uses the import time ($now)
    $emptyTicket = Ticket::query()->where('external_reference', 'TIME-EMPTY')->firstOrFail();
    expect($emptyTicket->created_at->format('Y-m-d H:i:s'))->toBe('2026-11-01 12:00:00');

    $errors = TicketImportError::query()
        ->where('ticket_import_id', $importId)
        ->get()
        ->keyBy('line');

    // Line 5: resolved row without resolved_at
    expect($errors[5]->reasons)->toContain('resolved_at: Required when status is resolved.');

    // Line 6: closed row without closed_at
    expect($errors[6]->reasons)->toContain('closed_at: Required when status is closed.');

    // Line 7: active row with resolved_at
    expect($errors[7]->reasons)->toContain('resolved_at: Must not be provided for active tickets.');

    // Line 8: future time
    expect($errors[8]->reasons)->toContain('created_at: Must not be in the future.');

    // Line 9: resolved before created
    expect($errors[9]->reasons)->toContain('resolved_at: Must not be earlier than created_at.');

    CarbonImmutable::setTestNow(null);
});

