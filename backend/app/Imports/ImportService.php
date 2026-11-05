<?php

namespace App\Imports;

use App\Enums\TicketImportStatus;
use App\Exceptions\ImportInProgressException;
use App\Jobs\ScanTicketImport;
use App\Models\TicketImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportService
{
    public function start(UploadedFile $file, User $admin): TicketImport
    {
        $lock = Cache::lock('ticket-import:start', 10);

        return $lock->block(5, function () use ($file, $admin) {
            $inProgress = TicketImport::query()
                ->whereIn('status', [TicketImportStatus::Pending, TicketImportStatus::Processing])
                ->exists();

            if ($inProgress) {
                throw new ImportInProgressException('Another import is in progress.');
            }

            $disk = config('imports.disk', 'local');
            $uuid = (string) Str::uuid();
            $path = $file->storeAs('imports', "{$uuid}.csv", $disk);

            $import = TicketImport::create([
                'user_id' => $admin->id,
                'original_filename' => $file->getClientOriginalName(),
                'disk' => $disk,
                'path' => $path,
                'status' => TicketImportStatus::Pending,
            ]);

            DB::afterCommit(function () use ($import) {
                ScanTicketImport::dispatch($import->id);
            });

            return $import;
        });
    }
}

