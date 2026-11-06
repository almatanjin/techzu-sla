<?php

use App\Models\Ticket;
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
});

it('uploads valid-3.csv, answers 202 pending, processes to completed with 3 imported tickets', function () {
    $file = importFixtureUpload('valid-3.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', [
            'file' => $file,
        ]);

    $response->assertStatus(202)
        ->assertJson([
            'status' => 'pending',
            'original_filename' => 'valid-3.csv',
        ]);

    $importId = $response->json('id');
    expect($importId)->not->toBeNull();

    $showResponse = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $showResponse->assertStatus(200)
        ->assertJson([
            'id' => $importId,
            'status' => 'completed',
            'total_rows' => 3,
            'processed_rows' => 3,
            'imported_rows' => 3,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
        ]);

    expect(Ticket::query()->whereIn('external_reference', ['REF-001', 'REF-002', 'REF-003'])->count())->toBe(3);
});

it('forbids agent and customer with 403, and unauthenticated callers with 401', function () {
    $file = importFixtureUpload('valid-3.csv');

    $this->postJson('/api/v1/admin/imports', ['file' => $file])
        ->assertStatus(401);

    $agent = User::query()->where('role', \App\Enums\UserRole::Agent)->firstOrFail();
    $agentToken = $agent->createToken('agent')->plainTextToken;

    $this->withToken($agentToken)
        ->post('/api/v1/admin/imports', ['file' => $file])
        ->assertStatus(403);

    $customer = User::query()->where('role', \App\Enums\UserRole::Customer)->firstOrFail();
    $customerToken = $customer->createToken('customer')->plainTextToken;

    $this->withToken($customerToken)
        ->post('/api/v1/admin/imports', ['file' => $file])
        ->assertStatus(403);
});

it('rejects a 51 MB file, an .xlsx and an empty file with 422 on file', function () {
    $tooLarge = \Illuminate\Http\UploadedFile::fake()->create('large.csv', 52224, 'text/csv');
    $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/imports', ['file' => $tooLarge])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    $xlsx = \Illuminate\Http\UploadedFile::fake()->create('tickets.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/imports', ['file' => $xlsx])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    $empty = \Illuminate\Http\UploadedFile::fake()->createWithContent('empty.csv', '');
    $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/imports', ['file' => $empty])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('rejects CSV text whose file name does not end in .csv or .txt with 422 on file', function () {
    // A real file on disk, so its type comes from its bytes (CSV text) and only its name says xlsx.
    $csvTextNamedXlsx = new \Illuminate\Http\UploadedFile(
        base_path('tests/Fixtures/imports/valid-3.csv'),
        'tickets.xlsx',
        'text/csv',
        null,
        true,
    );

    $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/imports', ['file' => $csvTextNamedXlsx])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    expect(\App\Models\TicketImport::count())->toBe(0);
});

it('answers 409 Another import is in progress. and creates nothing while an import is processing', function () {
    \App\Models\TicketImport::factory()->create([
        'user_id' => $this->admin->id,
        'status' => \App\Enums\TicketImportStatus::Processing,
    ]);

    $initialCount = \App\Models\TicketImport::count();
    $file = importFixtureUpload('valid-3.csv');

    $response = $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(409)
        ->assertJson([
            'message' => 'Another import is in progress.',
        ]);

    expect(\App\Models\TicketImport::count())->toBe($initialCount);
});

it('stores the uploaded file on the private disk under imports/', function () {
    $file = importFixtureUpload('valid-3.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $import = \App\Models\TicketImport::findOrFail($response->json('id'));

    expect($import->disk)->toBe('local')
        ->and($import->path)->toStartWith('imports/')
        ->and(Storage::disk('local')->exists($import->path))->toBeTrue();
});



