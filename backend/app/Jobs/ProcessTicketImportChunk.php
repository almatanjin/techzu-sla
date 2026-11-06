<?php

namespace App\Jobs;

use App\Enums\TicketActivityType;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Imports\ImportRowValidator;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketImport;
use App\Models\TicketImportError;
use App\Models\User;
use App\Services\Sla\SlaCalculator;
use App\Services\Tickets\TicketNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessTicketImportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30];

    public int $timeout = 80;

    public function __construct(
        public int $importId,
        public int $chunkIndex,
        public int $offset,
        public int $firstLine,
        public int $rowCount,
    ) {}

    public function handle(
        ImportRowValidator $validator,
        TicketNumberGenerator $generator,
        SlaCalculator $slaCalculator,
    ): void {
        try {
            $this->process(
                validator: $validator,
                generator: $generator,
                slaCalculator: $slaCalculator,
            );
        } catch (\Throwable $e) {
            $batch = $this->batch();
            if ($batch) {
                $batch->recordFailedJob((string) $this->chunkIndex, $e);
            }
            if ($this->attempts() < $this->tries && ! app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    private function process(
        ImportRowValidator $validator,
        TicketNumberGenerator $generator,
        SlaCalculator $slaCalculator,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Idempotency check
        $exists = DB::table('ticket_import_chunks')
            ->where('ticket_import_id', $this->importId)
            ->where('chunk_index', $this->chunkIndex)
            ->exists();

        if ($exists) {
            return;
        }

        $import = TicketImport::find($this->importId);
        if (! $import) {
            return;
        }

        $disk = Storage::disk($import->disk);
        $fullPath = $disk->path($import->path);
        $fp = @fopen($fullPath, 'r');
        if (! $fp) {
            throw new \RuntimeException("Unable to open file at {$fullPath}");
        }

        // Read header to build column mapping
        $rawHeader = fgetcsv($fp, escape: '\\');
        $columnMap = [];
        if ($rawHeader) {
            foreach ($rawHeader as $idx => $col) {
                if ($col === null) {
                    continue;
                }
                $clean = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $col));
                $clean = strtolower(str_replace(' ', '_', $clean));
                if (! isset($columnMap[$clean])) {
                    $columnMap[$clean] = $idx;
                }
            }
        }

        fseek($fp, $this->offset);

        $chunkRows = [];
        $currentLine = $this->firstLine;
        $readCount = 0;

        while ($readCount < $this->rowCount && ! feof($fp)) {
            $rawRow = fgetcsv($fp, escape: '\\');
            if ($rawRow === false) {
                break;
            }

            if ($rawRow === [null] || empty(array_filter($rawRow, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                $currentLine++;

                continue;
            }

            $mapped = [];
            foreach ($columnMap as $colName => $idx) {
                $mapped[$colName] = $rawRow[$idx] ?? null;
            }

            $chunkRows[] = [
                'line' => $currentLine,
                'raw' => $rawRow,
                'mapped' => $mapped,
            ];

            $currentLine++;
            $readCount++;
        }

        fclose($fp);

        if (empty($chunkRows)) {
            return;
        }

        $lastLine = $currentLine - 1;

        // Check which lines in this chunk are already recorded as in-file duplicates
        $inFileDuplicateLines = TicketImportError::query()
            ->where('ticket_import_id', $this->importId)
            ->whereBetween('line', [$this->firstLine, $lastLine])
            ->where('kind', 'duplicate')
            ->pluck('line')
            ->flip()
            ->all();

        // Extract emails and references for bulk lookups
        $emails = [];
        $references = [];
        foreach ($chunkRows as $r) {
            if (isset($inFileDuplicateLines[$r['line']])) {
                continue;
            }
            $email = strtolower(trim((string) ($r['mapped']['customer_email'] ?? '')));
            if ($email !== '') {
                $emails[] = $email;
            }
            $ref = trim((string) ($r['mapped']['external_reference'] ?? ''));
            if ($ref !== '') {
                $references[] = $ref;
            }
        }

        $customersByEmail = User::query()
            ->where('role', UserRole::Customer)
            ->whereIn('email', array_unique($emails))
            ->get()
            ->keyBy(fn ($u) => strtolower($u->email))
            ->all();

        $categoriesByName = Category::query()
            ->get()
            ->keyBy(fn ($c) => strtolower($c->name))
            ->all();

        $existingDbReferences = Ticket::query()
            ->whereIn('external_reference', array_unique($references))
            ->pluck('external_reference')
            ->map(fn ($r) => strtolower($r))
            ->flip()
            ->all();

        $now = CarbonImmutable::now();
        $validRows = [];
        $errorRecords = [];
        $chunkProcessed = 0;
        $chunkFailed = 0;
        $chunkDbDuplicates = 0;

        foreach ($chunkRows as $r) {
            $line = $r['line'];
            $rawRow = $r['raw'];
            $mapped = $r['mapped'];

            if (isset($inFileDuplicateLines[$line])) {
                $chunkProcessed++;

                continue;
            }

            $ref = trim((string) ($mapped['external_reference'] ?? ''));
            $refLower = strtolower($ref);

            if ($ref !== '' && isset($existingDbReferences[$refLower])) {
                $chunkProcessed++;
                $chunkDbDuplicates++;
                $errorRecords[] = [
                    'ticket_import_id' => $this->importId,
                    'line' => $line,
                    'external_reference' => $ref,
                    'kind' => 'duplicate',
                    'reasons' => json_encode(['external_reference: Already belongs to an existing ticket.']),
                    'raw_row' => json_encode(array_map(fn ($v) => mb_substr((string) $v, 0, 500), $rawRow)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            $validation = $validator->validate(
                line: $line,
                mapped: $mapped,
                rawRow: $rawRow,
                customersByEmail: $customersByEmail,
                categoriesByName: $categoriesByName,
                now: $now,
            );

            if ($validation['row'] === null) {
                $chunkProcessed++;
                $chunkFailed++;
                $errorRecords[] = [
                    'ticket_import_id' => $this->importId,
                    'line' => $line,
                    'external_reference' => $ref !== '' ? $ref : null,
                    'kind' => 'failed',
                    'reasons' => json_encode($validation['reasons']),
                    'raw_row' => json_encode(array_map(fn ($v) => mb_substr((string) $v, 0, 500), $rawRow)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } else {
                $validRows[] = $validation['row'];
            }
        }

        // Load the SLA policies and business calendar once for the whole chunk.
        $slaSnapshot = $slaCalculator->snapshot();

        DB::transaction(function () use (
            $generator,
            $slaSnapshot,
            $import,
            $validRows,
            $errorRecords,
            $chunkProcessed,
            $chunkFailed,
            $chunkDbDuplicates,
            $categoriesByName,
            $customersByEmail,
            $now
        ) {
            // Insert chunk idempotency marker
            DB::table('ticket_import_chunks')->insert([
                'ticket_import_id' => $this->importId,
                'chunk_index' => $this->chunkIndex,
                'created_at' => $now,
            ]);

            $chunkImported = 0;
            if (! empty($validRows)) {
                $count = count($validRows);
                $ticketNumbers = $generator->nextBlock($count);

                $ticketRecords = [];
                $validReferences = [];

                foreach ($validRows as $idx => $row) {
                    $createdAt = $row->createdAt ?? $now;
                    $deadlines = $slaSnapshot->deadlinesFor($row->priority, $createdAt);

                    $isActive = in_array($row->status, TicketStatus::active(), true);
                    $slaWarningNotifiedAt = null;
                    $slaBreachedAt = null;

                    if ($isActive) {
                        if ($deadlines->warningAt->lte($now)) {
                            $slaWarningNotifiedAt = $now;
                        }
                        if ($deadlines->dueAt->lte($now)) {
                            $slaBreachedAt = $now;
                        }
                    }

                    $customer = $customersByEmail[$row->customerEmail];
                    $category = $categoriesByName[strtolower($row->categoryName)];

                    $ticketRecords[] = [
                        'ticket_number' => $ticketNumbers[$idx],
                        'external_reference' => $row->externalReference,
                        'ticket_import_id' => $this->importId,
                        'subject' => $row->subject,
                        'description' => $row->description,
                        'priority' => $row->priority->value,
                        'status' => $row->status->value,
                        'category_id' => $category->id,
                        'customer_id' => $customer->id,
                        'created_by_id' => $import->user_id,
                        'assigned_agent_id' => null,
                        'first_assigned_at' => null,
                        'resolved_at' => $row->resolvedAt?->toDateTimeString(),
                        'closed_at' => $row->closedAt?->toDateTimeString(),
                        'lock_version' => 0,
                        'sla_policy_minutes' => $deadlines->policyMinutes,
                        'sla_started_at' => $deadlines->startedAt->toDateTimeString(),
                        'sla_warning_at' => $deadlines->warningAt->toDateTimeString(),
                        'sla_due_at' => $deadlines->dueAt->toDateTimeString(),
                        'sla_warning_notified_at' => $slaWarningNotifiedAt?->toDateTimeString(),
                        'sla_breached_at' => $slaBreachedAt?->toDateTimeString(),
                        'escalated_at' => null,
                        'created_at' => $createdAt->toDateTimeString(),
                        'updated_at' => $now->toDateTimeString(),
                    ];

                    $validReferences[] = $row->externalReference;
                }

                DB::table('tickets')->insert($ticketRecords);

                $insertedTickets = DB::table('tickets')
                    ->whereIn('external_reference', $validReferences)
                    ->get(['id', 'external_reference']);

                $activities = [];
                foreach ($insertedTickets as $t) {
                    $activities[] = [
                        'ticket_id' => $t->id,
                        'actor_id' => $import->user_id,
                        'type' => TicketActivityType::Imported->value,
                        'old_values' => null,
                        'new_values' => json_encode([
                            'import_id' => $this->importId,
                            'external_reference' => $t->external_reference,
                        ]),
                        'created_at' => $now->toDateTimeString(),
                    ];
                }

                DB::table('ticket_activities')->insert($activities);

                $chunkImported = $count;
            }

            if (! empty($errorRecords)) {
                DB::table('ticket_import_errors')->insert($errorRecords);
            }

            $totalProcessed = $chunkProcessed + $chunkImported;

            DB::table('ticket_imports')
                ->where('id', $this->importId)
                ->incrementEach([
                    'processed_rows' => $totalProcessed,
                    'imported_rows' => $chunkImported,
                    'failed_rows' => $chunkFailed,
                    'duplicate_rows' => $chunkDbDuplicates,
                ]);
        });
    }
}
