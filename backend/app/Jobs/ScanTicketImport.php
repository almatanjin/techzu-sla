<?php

namespace App\Jobs;

use App\Enums\TicketImportStatus;
use App\Models\TicketImport;
use App\Models\TicketImportError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanTicketImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 80;

    public function __construct(
        public int $importId
    ) {}

    public function handle(): void
    {
        $import = TicketImport::find($this->importId);
        if (! $import) {
            return;
        }

        $disk = Storage::disk($import->disk);
        if (! $disk->exists($import->path)) {
            $this->markFailed($import, 'The file could not be read.');

            return;
        }

        $fullPath = $disk->path($import->path);
        $fp = @fopen($fullPath, 'r');
        if (! $fp) {
            $this->markFailed($import, 'The file could not be read.');

            return;
        }

        try {
            $rawHeader = fgetcsv($fp, escape: '\\');
            if ($rawHeader === false || $rawHeader === [null] || empty($rawHeader)) {
                $this->markFailed($import, 'Missing required columns: external_reference, customer_email, subject, description, priority, category, status');
                fclose($fp);

                return;
            }

            $columnMap = [];
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

            $required = ['external_reference', 'customer_email', 'subject', 'description', 'priority', 'category', 'status'];
            $missing = [];
            foreach ($required as $req) {
                if (! array_key_exists($req, $columnMap)) {
                    $missing[] = $req;
                }
            }

            if (! empty($missing)) {
                $this->markFailed($import, 'Missing required columns: '.implode(', ', $missing));
                fclose($fp);

                return;
            }

            $chunkSize = (int) config('imports.chunk_size', 500);
            $seenReferences = [];
            $inFileDataDuplicates = [];
            $chunks = [];
            $currentChunkIndex = 0;
            $currentChunkOffset = ftell($fp);
            $currentChunkFirstLine = 2;
            $currentChunkRowCount = 0;
            $currentLine = 1;
            $totalRows = 0;
            $duplicateCount = 0;

            while (! feof($fp)) {
                $rowOffset = ftell($fp);
                $rawRow = fgetcsv($fp, escape: '\\');
                if ($rawRow === false) {
                    break;
                }

                $currentLine++;
                // Skip empty lines
                if ($rawRow === [null] || empty(array_filter($rawRow, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                $totalRows++;
                $refIdx = $columnMap['external_reference'];
                $ref = trim((string) ($rawRow[$refIdx] ?? ''));

                if ($ref !== '') {
                    $refKey = hash('xxh3', strtolower($ref));
                    if (isset($seenReferences[$refKey])) {
                        $firstLine = $seenReferences[$refKey];
                        $inFileDataDuplicates[] = [
                            'ticket_import_id' => $import->id,
                            'line' => $currentLine,
                            'external_reference' => $ref,
                            'kind' => 'duplicate',
                            'reasons' => json_encode(["Duplicate of line {$firstLine} in this file."]),
                            'raw_row' => json_encode(array_map(fn ($v) => mb_substr((string) $v, 0, 500), $rawRow)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $duplicateCount++;
                    } else {
                        $seenReferences[$refKey] = $currentLine;
                    }
                }

                $currentChunkRowCount++;
                if ($currentChunkRowCount === $chunkSize) {
                    $chunks[] = [
                        'chunk_index' => $currentChunkIndex,
                        'offset' => $currentChunkOffset,
                        'first_line' => $currentChunkFirstLine,
                        'row_count' => $currentChunkRowCount,
                    ];
                    $currentChunkIndex++;
                    $currentChunkOffset = ftell($fp);
                    $currentChunkFirstLine = $currentLine + 1;
                    $currentChunkRowCount = 0;
                }
            }

            fclose($fp);

            if ($currentChunkRowCount > 0) {
                $chunks[] = [
                    'chunk_index' => $currentChunkIndex,
                    'offset' => $currentChunkOffset,
                    'first_line' => $currentChunkFirstLine,
                    'row_count' => $currentChunkRowCount,
                ];
            }

            // Insert duplicate errors in chunks of 500
            foreach (array_chunk($inFileDataDuplicates, 500) as $errorBatch) {
                TicketImportError::insert($errorBatch);
            }

            $import->update([
                'total_rows' => $totalRows,
                'duplicate_rows' => $duplicateCount,
                'status' => TicketImportStatus::Processing,
                'started_at' => now(),
            ]);

            if ($totalRows === 0) {
                $import->update([
                    'status' => TicketImportStatus::Completed,
                    'finished_at' => now(),
                ]);

                return;
            }

            $importId = $import->id;
            $jobs = array_map(function ($chunk) use ($importId) {
                return new ProcessTicketImportChunk(
                    importId: $importId,
                    chunkIndex: $chunk['chunk_index'],
                    offset: $chunk['offset'],
                    firstLine: $chunk['first_line'],
                    rowCount: $chunk['row_count'],
                );
            }, $chunks);

            $batch = Bus::batch($jobs)
                ->allowFailures()
                ->finally(function (\Illuminate\Bus\Batch $batch) use ($importId) {
                    $imp = TicketImport::find($importId);
                    if (! $imp) {
                        return;
                    }
                    $finishedAt = now();
                    if ($batch->hasFailures() || $imp->status === TicketImportStatus::Failed) {
                        $imp->update([
                            'status' => TicketImportStatus::Failed,
                            'failure_message' => $imp->failure_message ?: 'Some rows could not be processed.',
                            'finished_at' => $finishedAt,
                        ]);
                    } else {
                        $imp->update([
                            'status' => TicketImportStatus::Completed,
                            'finished_at' => $finishedAt,
                        ]);
                    }
                })
                ->dispatch();

            $import->update(['batch_id' => $batch->id]);
        } catch (Throwable $e) {
            $imp = TicketImport::find($this->importId);
            if ($imp && $imp->status === TicketImportStatus::Failed && $imp->failure_message === 'Some rows could not be processed.') {
                return;
            }
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            $this->markFailed($import, 'The file could not be read.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $import = TicketImport::find($this->importId);
        if ($import) {
            $this->markFailed($import, 'The file could not be read.');
        }
    }

    private function markFailed(TicketImport $import, string $message): void
    {
        $import->update([
            'status' => TicketImportStatus::Failed,
            'failure_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
