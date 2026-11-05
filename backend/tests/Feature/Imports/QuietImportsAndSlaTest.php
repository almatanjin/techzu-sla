<?php

use App\Enums\SlaStatus;
use App\Enums\TicketActivityType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CategorySeeder::class);
    $this->seed(DemoUserSeeder::class);

    $this->admin = User::query()->where('email', 'admin@techzu-sla.test')->firstOrFail();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;
    $this->category = Category::query()->where('name', 'Billing')->firstOrFail();
});

it('sends nothing on importing 50 open rows and keeps all tickets unassigned after tickets:assign-unassigned', function () {
    Notification::fake();

    $file = importFixtureUpload('valid-50.csv');

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    Notification::assertNothingSent();

    $tickets = Ticket::query()->where('ticket_import_id', $importId)->get();
    expect($tickets->count())->toBe(50);
    foreach ($tickets as $ticket) {
        expect($ticket->assigned_agent_id)->toBeNull();
    }

    // Run automatic assignment retry
    Artisan::call('tickets:assign-unassigned');

    // All must stay unassigned
    $freshTickets = Ticket::query()->where('ticket_import_id', $importId)->get();
    foreach ($freshTickets as $ticket) {
        expect($ticket->assigned_agent_id)->toBeNull();
    }
});

it('silently records passed SLA deadlines on old active tickets without notifications or escalation', function () {
    Notification::fake();

    $importTime = CarbonImmutable::parse('2026-11-01 12:00:00');
    CarbonImmutable::setTestNow($importTime);

    // Critical row created a week ago (e.g. 2026-10-25 12:00:00)
    $csv = "external_reference,customer_email,subject,description,priority,category,status,created_at\n";
    $csv .= "OLD-CRIT,customer1@techzu-sla.test,Old Critical,Description,critical,Billing,open,2026-10-25 12:00:00\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('old-crit.csv', $csv);

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);
    $response->assertStatus(202);

    $ticket = Ticket::query()->where('external_reference', 'OLD-CRIT')->firstOrFail();

    expect($ticket->slaStatus($importTime))->toBe(SlaStatus::Breached)
        ->and($ticket->sla_breached_at->toDateTimeString())->toBe($importTime->toDateTimeString())
        ->and($ticket->sla_warning_notified_at->toDateTimeString())->toBe($importTime->toDateTimeString())
        ->and($ticket->escalated_at)->toBeNull();

    // Run SLA check command
    Artisan::call('tickets:check-sla');

    $ticket->refresh();
    expect($ticket->escalated_at)->toBeNull();

    // Verify no SLA warning or escalated activity entries exist
    $activityTypes = $ticket->activities()->pluck('type')->all();
    expect($activityTypes)->not->toContain(TicketActivityType::SlaWarning)
        ->and($activityTypes)->not->toContain(TicketActivityType::Escalated);

    Notification::assertNothingSent();

    CarbonImmutable::setTestNow(null);
});

it('warns a recent active Low ticket when time passes its warning deadline', function () {
    Notification::fake();

    $importTime = CarbonImmutable::parse('2026-11-02 09:10:00'); // Monday morning (working day)
    CarbonImmutable::setTestNow($importTime);

    // Created 10 minutes ago
    $csv = "external_reference,customer_email,subject,description,priority,category,status,created_at\n";
    $csv .= "RECENT-LOW,customer1@techzu-sla.test,Recent Low,Description,low,Billing,open,2026-11-02 09:00:00\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('recent-low.csv', $csv);

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);
    $response->assertStatus(202);

    $ticket = Ticket::query()->where('external_reference', 'RECENT-LOW')->firstOrFail();

    expect($ticket->sla_warning_notified_at)->toBeNull()
        ->and($ticket->sla_breached_at)->toBeNull();

    // Travel to 1 minute past warning time
    $warningTime = CarbonImmutable::instance($ticket->sla_warning_at)->addMinute();
    $this->travelTo($warningTime);

    Artisan::call('tickets:check-sla');

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->not->toBeNull();

    $activityTypes = $ticket->activities()->pluck('type')->all();
    expect($activityTypes)->toContain(TicketActivityType::SlaWarning);

    $this->travelBack();
    CarbonImmutable::setTestNow(null);
});

it('sets SLA status to met for a historical resolved Critical ticket created and resolved within SLA', function () {
    // 2026-10-01 was a Thursday (working day)
    // 09:00 Asia/Dhaka is working hours; resolved 10:00 (1 hour resolution, critical SLA is 60 minutes or more)
    $csv = "external_reference,customer_email,subject,description,priority,category,status,created_at,resolved_at\n";
    $csv .= "HIST-RESOLVED,customer1@techzu-sla.test,Resolved Critical,Description,critical,Billing,resolved,2026-10-01 09:00,2026-10-01 10:00\n";

    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('hist-resolved.csv', $csv);

    $response = $this->withToken($this->adminToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);
    $response->assertStatus(202);

    $ticket = Ticket::query()->where('external_reference', 'HIST-RESOLVED')->firstOrFail();

    expect($ticket->slaStatus())->toBe(SlaStatus::Met);
});

