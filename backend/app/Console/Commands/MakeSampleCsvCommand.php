<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Console\Command;

class MakeSampleCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:make-sample-csv
                            {--rows=1000 : Number of rows to generate}
                            {--path= : File path to write CSV to}
                            {--invalid=0 : Percentage of invalid rows (0-100)}
                            {--duplicates=0 : Percentage of duplicate rows (0-100)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a sample ticket import CSV with optional invalid and duplicate rows';

    public function handle(): int
    {
        $rows = (int) $this->option('rows');
        $invalidPercent = (float) $this->option('invalid');
        $duplicatePercent = (float) $this->option('duplicates');

        $path = $this->option('path');
        if (! $path) {
            $path = storage_path("app/private/samples/tickets-{$rows}.csv");
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($path, 'w');
        if (! $fp) {
            $this->error("Cannot open file for writing: {$path}");

            return self::FAILURE;
        }

        $headers = [
            'external_reference',
            'customer_email',
            'subject',
            'description',
            'priority',
            'category',
            'status',
            'created_at',
            'resolved_at',
            'closed_at',
        ];
        fputcsv($fp, $headers);

        $customerEmails = User::query()->where('role', UserRole::Customer)->pluck('email')->all();
        if (empty($customerEmails)) {
            $customerEmails = ['customer1@techzu-sla.test', 'customer2@techzu-sla.test'];
        }

        $categoryNames = Category::query()->pluck('name')->all();
        if (empty($categoryNames)) {
            $categoryNames = ['Billing', 'Technical', 'General'];
        }

        $priorities = ['low', 'medium', 'high', 'critical'];
        $statuses = ['open', 'in_progress', 'waiting_for_customer', 'resolved', 'closed'];

        $numDuplicates = (int) round(($rows * $duplicatePercent) / 100);
        $numInvalid = (int) round(($rows * $invalidPercent) / 100);

        // Distribute invalid and duplicate row indexes across the file
        $duplicateIndexes = [];
        if ($numDuplicates > 0 && $rows > 1) {
            $step = max(1, (int) floor($rows / $numDuplicates));
            for ($d = 0; $d < $numDuplicates; $d++) {
                $idx = min($rows, 2 + ($d * $step));
                $duplicateIndexes[$idx] = true;
            }
        }

        $invalidIndexes = [];
        if ($numInvalid > 0) {
            $step = max(1, (int) floor($rows / $numInvalid));
            for ($inv = 0; $inv < $numInvalid; $inv++) {
                $idx = min($rows, 3 + ($inv * $step));
                if (! isset($duplicateIndexes[$idx])) {
                    $invalidIndexes[$idx] = true;
                } elseif ($idx + 1 <= $rows) {
                    $invalidIndexes[$idx + 1] = true;
                }
            }
        }

        for ($i = 1; $i <= $rows; $i++) {
            $isDuplicate = isset($duplicateIndexes[$i]);
            $isInvalid = isset($invalidIndexes[$i]);

            if ($isDuplicate) {
                $ref = 'SAMPLE-1';
            } else {
                $ref = "SAMPLE-{$i}";
            }

            if ($isInvalid) {
                $email = "unknown-{$i}@nonexistent-domain.test";
                $priority = 'urgent';
            } else {
                $email = $customerEmails[($i - 1) % count($customerEmails)];
                $priority = $priorities[($i - 1) % count($priorities)];
            }

            $cat = $categoryNames[($i - 1) % count($categoryNames)];
            $status = $statuses[($i - 1) % count($statuses)];

            $createdAt = '2026-10-01 09:00';
            $resolvedAt = '';
            $closedAt = '';

            if ($status === 'resolved') {
                $resolvedAt = '2026-10-01 11:00';
            } elseif ($status === 'closed') {
                $closedAt = '2026-10-01 12:00';
            }

            fputcsv($fp, [
                $ref,
                $email,
                "Sample Ticket {$i}",
                "Description for sample ticket {$i}.",
                $priority,
                $cat,
                $status,
                $createdAt,
                $resolvedAt,
                $closedAt,
            ]);
        }

        fclose($fp);

        $this->info("Wrote {$rows} rows to {$path}");

        return self::SUCCESS;
    }
}

