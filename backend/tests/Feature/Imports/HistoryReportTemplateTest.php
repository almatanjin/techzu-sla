<?php

use App\Enums\TicketImportStatus;
use App\Models\Ticket;
use App\Models\TicketImport;
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

it('lists imports newest first with counts, status and admin name, 20 per page', function () {
    $adminSara = User::factory()->admin()->create(['name' => 'Sara']);

    TicketImport::factory()->create([
        'user_id' => $adminSara->id,
        'created_at' => now()->subHours(3),
        'status' => TicketImportStatus::Completed,
        'total_rows' => 10,
        'processed_rows' => 10,
        'imported_rows' => 10,
    ]);

    TicketImport::factory()->create([
        'user_id' => $this->admin->id,
        'created_at' => now()->subHours(2),
        'status' => TicketImportStatus::Failed,
        'total_rows' => 5,
        'processed_rows' => 2,
        'imported_rows' => 1,
        'failed_rows' => 1,
    ]);

    TicketImport::factory()->create([
        'user_id' => $adminSara->id,
        'created_at' => now()->subHour(),
        'status' => TicketImportStatus::Completed,
        'total_rows' => 20,
        'processed_rows' => 20,
        'imported_rows' => 18,
        'failed_rows' => 2,
    ]);

    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/imports');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('data.0.total_rows', 20)
        ->assertJsonPath('data.0.user_name', 'Sara')
        ->assertJsonPath('data.1.total_rows', 5)
        ->assertJsonPath('data.1.user_name', 'Demo Administrator')
        ->assertJsonPath('data.2.total_rows', 10)
        ->assertJsonPath('data.2.user_name', 'Sara');
});

it('downloads errors.csv with failed and duplicate rows in line order with reasons joined by semicolon', function () {
    $file = importFixtureUpload('mixed-rows.csv');

    $res = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);
    $res->assertStatus(202);
    $importId = $res->json('id');

    $download = $this->withToken($this->adminToken)
        ->get("/api/v1/admin/imports/{$importId}/errors.csv");

    $download->assertStatus(200)
        ->assertHeader('Content-Disposition', "attachment; filename=import-{$importId}-errors.csv");

    $content = $download->streamedContent();
    $lines = explode("\n", trim($content));

    // Header line
    expect($lines[0])->toBe('line,external_reference,reasons');

    // 8 failed rows + 1 duplicate row = 9 error lines + 1 header = 10 lines
    expect(count($lines))->toBe(10);

    // Verify ordering by line number
    $prevLine = 0;
    for ($i = 1; $i < count($lines); $i++) {
        $row = str_getcsv($lines[$i]);
        $currentLineNum = (int) $row[0];
        expect($currentLineNum)->toBeGreaterThan($prevLine);
        $prevLine = $currentLineNum;
    }

    // Verify line 10 has both reasons joined by "; "
    // (Line 10 was ERR-8: unknown email and urgent priority)
    $line10Row = str_getcsv($lines[8]); // 8th error is line 10
    expect($line10Row[0])->toBe('10')
        ->and($line10Row[2])->toContain('customer_email: No customer account has this email.; priority: Must be one of low, medium, high, critical.');
});

it('returns header only when downloading errors.csv for an import with no skipped rows', function () {
    $file = importFixtureUpload('valid-3.csv');

    $res = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);
    $res->assertStatus(202);
    $importId = $res->json('id');

    $download = $this->withToken($this->adminToken)
        ->get("/api/v1/admin/imports/{$importId}/errors.csv");

    $download->assertStatus(200);
    $content = trim($download->streamedContent());

    expect($content)->toBe('line,external_reference,reasons');
});

it('completes with 2 imported rows when importing template.csv unchanged', function () {
    $templateRes = $this->withToken($this->adminToken)
        ->get('/api/v1/admin/imports/template.csv');

    $templateRes->assertStatus(200);
    $csvContent = $templateRes->streamedContent();

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('template.csv', $csvContent);

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 2,
            'processed_rows' => 2,
            'imported_rows' => 2,
            'failed_rows' => 0,
            'duplicate_rows' => 0,
        ]);

    expect(Ticket::query()->whereIn('external_reference', ['TPL-001', 'TPL-002'])->count())->toBe(2);
});

