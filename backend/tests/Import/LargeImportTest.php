<?php

use App\Enums\TicketImportStatus;
use App\Models\Ticket;
use App\Models\TicketImport;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CategorySeeder::class);
    $this->seed(DemoUserSeeder::class);

    if (\Illuminate\Support\Facades\DB::table('business_hours')->count() === 0) {
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
        foreach ($defaults as $d) {
            \Illuminate\Support\Facades\DB::table('business_hours')->insert([...$d, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
    if (\Illuminate\Support\Facades\DB::table('sla_policies')->count() === 0) {
        $now = now();
        $policies = [
            ['priority' => 'low', 'duration_minutes' => 2880, 'warning_threshold_percentage' => 75],
            ['priority' => 'medium', 'duration_minutes' => 1440, 'warning_threshold_percentage' => 75],
            ['priority' => 'high', 'duration_minutes' => 480, 'warning_threshold_percentage' => 75],
            ['priority' => 'critical', 'duration_minutes' => 120, 'warning_threshold_percentage' => 75],
        ];
        foreach ($policies as $p) {
            \Illuminate\Support\Facades\DB::table('sla_policies')->insert([...$p, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    $this->admin = User::query()->where('email', 'admin@techzu-sla.test')->firstOrFail();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;
});

it('imports 100,000 valid rows with peak memory increase under 64 MB', function () {
    $tempPath = sys_get_temp_dir().'/large-100k.csv';
    if (file_exists($tempPath)) {
        unlink($tempPath);
    }

    $genStart = microtime(true);
    Artisan::call('tickets:make-sample-csv', [
        '--rows' => 100000,
        '--path' => $tempPath,
    ]);
    $genDuration = round(microtime(true) - $genStart, 2);

    expect(file_exists($tempPath))->toBeTrue();

    // Reset peak memory usage before running import
    gc_collect_cycles();
    memory_reset_peak_usage();
    $initialMemory = memory_get_usage();

    $importStart = microtime(true);
    $file = new \Illuminate\Http\UploadedFile($tempPath, 'large-100k.csv', 'text/csv', null, true);

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $importDuration = round(microtime(true) - $importStart, 2);
    $peakMemory = memory_get_peak_usage();
    $memoryIncrease = $peakMemory - $initialMemory;
    $memoryMb = round($memoryIncrease / (1024 * 1024), 2);

    $import = TicketImport::findOrFail($importId);

    // Record stats in log or stdout for README
    echo "\n[LargeImportTest] Duration: {$importDuration}s, Peak memory increase: {$memoryMb} MB\n";

    expect($import->status)->toBe(TicketImportStatus::Completed)
        ->and($import->total_rows)->toBe(100000)
        ->and($import->processed_rows)->toBe(100000)
        ->and($import->imported_rows)->toBe(100000)
        ->and($import->failed_rows)->toBe(0)
        ->and($import->duplicate_rows)->toBe(0)
        ->and(Ticket::query()->where('ticket_import_id', $importId)->count())->toBe(100000)
        ->and($memoryIncrease)->toBeLessThan(64 * 1024 * 1024);

    if (file_exists($tempPath)) {
        unlink($tempPath);
    }
})->group('large-import');

