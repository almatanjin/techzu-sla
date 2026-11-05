<?php

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketImportError;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CategorySeeder::class);
    $this->seed(DemoUserSeeder::class);

    $this->admin = User::query()->where('email', 'admin@techzu-sla.test')->firstOrFail();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

    $this->deactivatedCustomer = User::factory()->customer()->create([
        'email' => 'deactivated@techzu-sla.test',
        'is_active' => false,
    ]);
});

it('validates rows from a mixed fixture and reports per-row failure reasons', function () {
    $file = importFixtureUpload('mixed-rows.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 12,
            'processed_rows' => 12,
            'imported_rows' => 3,
            'failed_rows' => 8,
            'duplicate_rows' => 1,
        ]);

    // Check errors by line
    $errorsByLine = TicketImportError::query()
        ->where('ticket_import_id', $importId)
        ->get()
        ->keyBy('line');

    // Line 3: unknown email
    expect($errorsByLine[3]->reasons)->toContain('customer_email: No customer account has this email.');

    // Line 4: agent email
    expect($errorsByLine[4]->reasons)->toContain('customer_email: No customer account has this email.');

    // Line 5: urgent priority
    expect($errorsByLine[5]->reasons)->toContain('priority: Must be one of low, medium, high, critical.');

    // Line 6: unknown category
    expect($errorsByLine[6]->reasons)->toContain('category: Category does not exist.');

    // Line 7: missing subject
    expect($errorsByLine[7]->reasons)->toContain('subject: Required.');

    // Line 8: 201-character subject
    expect($errorsByLine[8]->reasons)->toContain('subject: Must not exceed 200 characters.');

    // Line 9: missing external_reference
    expect($errorsByLine[9]->reasons)->toContain('external_reference: Required.');

    // Line 10: two problems
    expect($errorsByLine[10]->reasons)->toContain('customer_email: No customer account has this email.')
        ->and($errorsByLine[10]->reasons)->toContain('priority: Must be one of low, medium, high, critical.');

    // Valid imported tickets: Line 2 (VAL-1), Line 11 (VAL-2 with HIGH / billing), Line 12 (VAL-3 with deactivated customer)
    expect(Ticket::query()->where('external_reference', 'VAL-1')->exists())->toBeTrue()
        ->and(Ticket::query()->where('external_reference', 'VAL-2')->exists())->toBeTrue()
        ->and(Ticket::query()->where('external_reference', 'VAL-3')->exists())->toBeTrue();
});

it('rejects row with invalid UTF-8 and reports Row is not valid UTF-8.', function () {
    $file = importFixtureUpload('invalid-utf8.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 1,
            'failed_rows' => 1,
            'imported_rows' => 0,
        ]);

    $error = TicketImportError::query()
        ->where('ticket_import_id', $importId)
        ->firstOrFail();

    expect($error->reasons)->toContain('Row is not valid UTF-8.');
});
