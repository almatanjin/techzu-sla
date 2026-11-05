<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTicketImportRequest;
use App\Http\Resources\TicketImportResource;
use App\Imports\ImportService;
use App\Models\Category;
use App\Models\TicketImport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TicketImport::class);

        $imports = TicketImport::query()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return TicketImportResource::collection($imports);
    }

    public function store(StoreTicketImportRequest $request): JsonResponse
    {
        Gate::authorize('create', TicketImport::class);

        $file = $request->file('file');
        $import = $this->importService->start($file, $request->user());

        return (new TicketImportResource($import))
            ->response()
            ->setStatusCode(202);
    }

    public function show(TicketImport $import): TicketImportResource
    {
        Gate::authorize('view', $import);

        $import->loadMissing('user:id,name');

        return new TicketImportResource($import);
    }

    public function errors(TicketImport $import): StreamedResponse
    {
        Gate::authorize('view', $import);

        $filename = "import-{$import->id}-errors.csv";

        return response()->streamDownload(function () use ($import) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, ['line', 'external_reference', 'reasons']);

            $import->errors()->orderBy('line')->chunk(500, function ($errors) use ($fp) {
                foreach ($errors as $err) {
                    $reasonsStr = is_array($err->reasons) ? implode('; ', $err->reasons) : (string) $err->reasons;
                    fputcsv($fp, [
                        $err->line,
                        $err->external_reference ?? '',
                        $reasonsStr,
                    ]);
                }
            });

            fclose($fp);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('downloadTemplate', TicketImport::class);

        $firstCustomer = User::query()->where('role', \App\Enums\UserRole::Customer)->first();
        $secondCustomer = User::query()->where('role', \App\Enums\UserRole::Customer)->skip(1)->first() ?? $firstCustomer;
        $firstCategory = Category::query()->first();
        $secondCategory = Category::query()->skip(1)->first() ?? $firstCategory;

        $c1Email = $firstCustomer?->email ?? 'customer1@techzu-sla.test';
        $c2Email = $secondCustomer?->email ?? 'customer2@techzu-sla.test';
        $cat1Name = $firstCategory?->name ?? 'General';
        $cat2Name = $secondCategory?->name ?? 'Billing';

        $headers = ['external_reference', 'customer_email', 'subject', 'description', 'priority', 'category', 'status', 'created_at', 'resolved_at', 'closed_at'];
        $row1 = ['TPL-001', $c1Email, 'Cannot access billing portal', 'Invoices fail to load with 500 error', 'high', $cat1Name, 'open', '2026-10-01 09:00', '', ''];
        $row2 = ['TPL-002', $c2Email, 'VPN connectivity issues', 'VPN reconnects frequently during peak hours', 'medium', $cat2Name, 'resolved', '2026-10-01 09:00', '2026-10-01 11:00', ''];

        return response()->streamDownload(function () use ($headers, $row1, $row2) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, $headers);
            fputcsv($fp, $row1);
            fputcsv($fp, $row2);
            fclose($fp);
        }, 'template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
