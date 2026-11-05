<?php

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CategorySeeder::class);
    $this->seed(DemoUserSeeder::class);

    $this->admin = User::query()->where('email', 'admin@techzu-sla.test')->firstOrFail();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;
});

it('writes 201 lines with full header and prints path when --rows=200', function () {
    $tempPath = sys_get_temp_dir().'/sample-200.csv';
    if (file_exists($tempPath)) {
        unlink($tempPath);
    }

    $exitCode = Artisan::call('tickets:make-sample-csv', [
        '--rows' => 200,
        '--path' => $tempPath,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain($tempPath)
        ->and(file_exists($tempPath))->toBeTrue();

    $lines = file($tempPath);
    expect(count($lines))->toBe(201);

    $header = trim($lines[0]);
    expect($header)->toBe('external_reference,customer_email,subject,description,priority,category,status,created_at,resolved_at,closed_at');

    unlink($tempPath);
});

it('generates about 20 invalid and 20 duplicate rows with --invalid=10 --duplicates=10 which an import reports as failed and duplicate', function () {
    $tempPath = sys_get_temp_dir().'/sample-mixed.csv';
    if (file_exists($tempPath)) {
        unlink($tempPath);
    }

    Artisan::call('tickets:make-sample-csv', [
        '--rows' => 200,
        '--path' => $tempPath,
        '--invalid' => 10,
        '--duplicates' => 10,
    ]);

    $file = new \Illuminate\Http\UploadedFile($tempPath, 'sample-mixed.csv', 'text/csv', null, true);

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->adminToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'total_rows' => 200,
            'processed_rows' => 200,
            'failed_rows' => 20,
            'duplicate_rows' => 20,
            'imported_rows' => 160,
        ]);

    unlink($tempPath);
});

