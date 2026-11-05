<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketImport;
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
    $this->customer = User::query()->where('role', \App\Enums\UserRole::Customer)->firstOrFail();
    $this->category = Category::query()->firstOrFail();

    $this->deactivatedCustomer = User::factory()->customer()->create([
        'email' => 'deactivated@techzu-sla.test',
        'is_active' => false,
    ]);
});

it('imports line 2 and reports line 7 as duplicate of line 2 when sharing LEG-42', function () {
    $file = importFixtureUpload('duplicate-in-file.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 6,
            'processed_rows' => 6,
            'imported_rows' => 5,
            'duplicate_rows' => 1,
            'failed_rows' => 0,
        ]);

    expect(Ticket::query()->where('external_reference', 'LEG-42')->count())->toBe(1);

    $error = TicketImportError::query()
        ->where('ticket_import_id', $importId)
        ->where('line', 7)
        ->firstOrFail();

    expect($error->kind)->toBe('duplicate')
        ->and($error->reasons)->toContain('Duplicate of line 2 in this file.');
});

it('gives 0 imported and 100 duplicates on re-importing valid-100.csv, keeping 100 tickets', function () {
    $file1 = importFixtureUpload('valid-100.csv');

    $res1 = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file1]);
    $res1->assertStatus(202);
    $id1 = $res1->json('id');

    $show1 = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$id1}");
    $show1->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 100,
            'imported_rows' => 100,
            'duplicate_rows' => 0,
        ]);

    expect(Ticket::query()->where('external_reference', 'like', 'HUNDRED-%')->count())->toBe(100);

    // Second import of the same file
    $file2 = importFixtureUpload('valid-100.csv');

    $res2 = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file2]);
    $res2->assertStatus(202);
    $id2 = $res2->json('id');

    $show2 = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$id2}");
    $show2->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 100,
            'processed_rows' => 100,
            'imported_rows' => 0,
            'duplicate_rows' => 100,
            'failed_rows' => 0,
        ]);

    expect(Ticket::query()->where('external_reference', 'like', 'HUNDRED-%')->count())->toBe(100);
});

it('counts an external reference already existing in the database as a duplicate', function () {
    Ticket::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by_id' => $this->customer->id,
        'category_id' => $this->category->id,
        'external_reference' => 'REF-001',
    ]);

    $file = importFixtureUpload('valid-3.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);
    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 3,
            'processed_rows' => 3,
            'imported_rows' => 2,
            'duplicate_rows' => 1,
            'failed_rows' => 0,
        ]);

    $error = TicketImportError::query()
        ->where('ticket_import_id', $importId)
        ->where('external_reference', 'REF-001')
        ->firstOrFail();

    expect($error->kind)->toBe('duplicate')
        ->and($error->reasons)->toContain('external_reference: Already belongs to an existing ticket.');
});

it('verifies imported + failed + duplicate = processed = total holds for the mixed fixture', function () {
    $file = importFixtureUpload('mixed-rows.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);
    $response->assertStatus(202);
    $importId = $response->json('id');

    $import = TicketImport::findOrFail($importId);

    expect($import->status->value)->toBe('completed')
        ->and($import->processed_rows)->toBe($import->total_rows)
        ->and($import->imported_rows + $import->failed_rows + $import->duplicate_rows)->toBe($import->processed_rows)
        ->and($import->total_rows)->toBe(12)
        ->and($import->imported_rows)->toBe(3)
        ->and($import->failed_rows)->toBe(8)
        ->and($import->duplicate_rows)->toBe(1);
});
