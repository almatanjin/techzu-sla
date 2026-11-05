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

it('imports by column name with reordered header, UTF-8 BOM, and extra Notes column', function () {
    $file = importFixtureUpload('header-reordered-bom.csv');

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
            'imported_rows' => 1,
        ]);

    $ticket = Ticket::query()->where('external_reference', 'BOM-1')->firstOrFail();
    expect($ticket->subject)->toBe('BOM ticket')
        ->and($ticket->description)->toBe('Testing BOM and reordered header')
        ->and($ticket->status->value)->toBe('open');
});

it('ends failed with Missing required columns: category when category header is missing', function () {
    $file = importFixtureUpload('missing-category.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'failed',
            'failure_message' => 'Missing required columns: category',
        ]);

    expect(Ticket::query()->where('external_reference', 'NO-CAT-1')->exists())->toBeFalse();
});

it('imports multiline quoted description spanning three physical lines as one row', function () {
    $file = importFixtureUpload('multiline-quoted.csv');

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
            'imported_rows' => 1,
        ]);

    $ticket = Ticket::query()->where('external_reference', 'MULTI-1')->firstOrFail();
    expect($ticket->description)->toBe("Line 1 of description\nLine 2 of description\nLine 3 of description");
});

it('skips and does not count blank lines between valid rows', function () {
    $file = importFixtureUpload('blank-lines.csv');

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
        ]);

    expect(Ticket::query()->whereIn('external_reference', ['BLANK-1', 'BLANK-2'])->count())->toBe(2);
});

