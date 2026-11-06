<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(CategorySeeder::class);
    $this->seed(DemoUserSeeder::class);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 06:00:00'));

    $this->adminToken = User::query()->where('email', 'admin@techzu-sla.test')->firstOrFail()
        ->createToken('admin')->plainTextToken;
});

/**
 * Imports a file of $rows valid tickets (one chunk) and returns the number of database queries it ran.
 */
function queriesForImportOf(int $rows, string $prefix): int
{
    $priorities = ['low', 'medium', 'high', 'critical'];
    $statuses = ['open', 'in_progress', 'resolved', 'closed'];
    $lines = ['external_reference,customer_email,subject,description,priority,category,status,created_at,resolved_at,closed_at'];
    for ($i = 1; $i <= $rows; $i++) {
        $status = $statuses[$i % 4];
        $resolved = in_array($status, ['resolved', 'closed'], true) ? '2026-11-02 10:00:00' : '';
        $closed = $status === 'closed' ? '2026-11-02 12:00:00' : '';
        $lines[] = "{$prefix}-{$i},customer".(($i % 5) + 1)."@techzu-sla.test,Subject {$i},Description {$i},{$priorities[$i % 4]},Billing,{$status},2026-11-01 09:00:00,{$resolved},{$closed}";
    }
    $file = UploadedFile::fake()->createWithContent('tickets.csv', implode("\n", $lines)."\n");

    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    test()->withToken(test()->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file])
        ->assertStatus(202);

    return $count;
}

it('runs the same number of database queries for a 10-row and a 200-row import chunk', function () {
    // The first import of the year also creates one-time records, such as the ticket number sequence.
    queriesForImportOf(1, 'WARMUP');

    $small = queriesForImportOf(10, 'SMALL');
    $large = queriesForImportOf(200, 'LARGE');

    expect(DB::table('tickets')->count())->toBe(211);
    expect($large)->toBe($small);
});
