<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->customer = User::factory()->customer()->create();
    $this->agent = User::factory()->agent()->unavailable()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;
    $this->category = Category::factory()->create(['is_active' => true]);
});

it('computes deadlines when an agent creates a critical ticket at Thursday 17:00', function () {
    // Thursday 17:00 in Asia/Dhaka is 11:00 UTC
    $now = CarbonImmutable::parse('2026-11-05 17:00:00', 'Asia/Dhaka');
    $this->travelTo($now);

    $response = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Production database down',
            'description' => 'Complete outage in production cluster.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);

    $response->assertStatus(201);

    $sla = $response->json('sla');
    expect($sla)->not->toBeNull()
        ->and($sla['policy_minutes'])->toBe(120);

    expect(CarbonImmutable::parse($sla['started_at'])->toISOString())
        ->toBe($now->setTimezone('UTC')->toISOString())
        ->and(CarbonImmutable::parse($sla['warning_at'])->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe('2026-11-08 09:30')
        ->and(CarbonImmutable::parse($sla['due_at'])->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe('2026-11-08 10:00');
});


test('customer responses contain no sla key while admin responses do', function () {
    $admin = User::factory()->admin()->create();
    $adminToken = $admin->createToken('admin')->plainTextToken;
    $customerToken = $this->customer->createToken('customer')->plainTextToken;

    // Customer creates ticket
    $createResponse = $this->withToken($customerToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Cannot log in',
            'description' => 'Password reset is not sending emails.',
            'category_id' => $this->category->id,
        ]);

    $createResponse->assertStatus(201);
    expect($createResponse->json())->not->toHaveKey('sla');

    $ticketNumber = $createResponse->json('ticket_number');

    // Customer shows ticket
    $showResponse = $this->withToken($customerToken)
        ->getJson("/api/v1/tickets/{$ticketNumber}");

    $showResponse->assertOk();
    expect($showResponse->json())->not->toHaveKey('sla');

    // Customer lists tickets
    $listResponse = $this->withToken($customerToken)
        ->getJson('/api/v1/tickets');

    $listResponse->assertOk();
    $items = $listResponse->json('data');
    expect($items)->not->toBeEmpty();
    foreach ($items as $item) {
        expect($item)->not->toHaveKey('sla');
    }

    // Admin shows ticket
    $this->app['auth']->forgetGuards();
    $adminShowResponse = $this->withToken($adminToken)
        ->getJson("/api/v1/tickets/{$ticketNumber}");

    $adminShowResponse->assertOk();
    expect($adminShowResponse->json())->toHaveKey('sla')
        ->and($adminShowResponse->json('sla.status'))->toBe('within');

    // Admin lists tickets
    $adminListResponse = $this->withToken($adminToken)
        ->getJson('/api/v1/tickets');

    $adminListResponse->assertOk();
    $adminItems = $adminListResponse->json('data');
    expect($adminItems)->not->toBeEmpty();
    foreach ($adminItems as $item) {
        expect($item)->toHaveKey('sla');
    }
});


it('calculates expected due time for each priority created on Sunday 09:00', function (string $priority, string $expectedDue) {
    $now = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');
    $this->travelTo($now);

    $response = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => "Test priority {$priority}",
            'description' => "Checking SLA due time calculation for {$priority}.",
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => $priority,
        ]);

    $response->assertStatus(201);
    $dueIso = $response->json('sla.due_at');
    expect(CarbonImmutable::parse($dueIso)->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe($expectedDue);
})->with([
    ['low', '2026-11-15 12:00'],
    ['medium', '2026-11-10 15:00'],
    ['high', '2026-11-08 17:00'],
    ['critical', '2026-11-08 11:00'],
]);


test('changing a medium ticket created Sunday 09:00 to high at 10:00 makes it due Sunday 17:00', function () {
    $createdTime = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');
    $this->travelTo($createdTime);

    $createResponse = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Issue needing reclassification',
            'description' => 'Created as medium priority.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'medium',
        ]);

    $createResponse->assertStatus(201);
    $ticketNumber = $createResponse->json('ticket_number');

    // At 10:00, agent raises priority to High
    $updateTime = CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Dhaka');
    $this->travelTo($updateTime);

    $updateResponse = $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticketNumber}", [
            'priority' => 'high',
            'lock_version' => 0,
        ]);

    $updateResponse->assertOk();
    $sla = $updateResponse->json('sla');
    expect($sla['policy_minutes'])->toBe(480)
        ->and(CarbonImmutable::parse($sla['due_at'])->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe('2026-11-08 17:00');
});


test('subject edit, moving to waiting for customer, resolving and reopening leave warning_at and due_at unchanged', function () {
    $createdTime = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');
    $this->travelTo($createdTime);

    $createResponse = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Initial subject',
            'description' => 'Detailed issue description.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);

    $ticketNumber = $createResponse->json('ticket_number');
    $warningAt = $createResponse->json('sla.warning_at');
    $dueAt = $createResponse->json('sla.due_at');

    // 1. Subject edit
    $this->travelTo($createdTime->addMinutes(15));
    $patchResponse = $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticketNumber}", [
            'subject' => 'Updated subject',
            'lock_version' => 0,
        ]);
    expect($patchResponse->json('sla.warning_at'))->toBe($warningAt)
        ->and($patchResponse->json('sla.due_at'))->toBe($dueAt);

    // 2. Move to in_progress then waiting_for_customer
    $this->travelTo($createdTime->addMinutes(25));
    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticketNumber}/status", [
            'status' => 'in_progress',
            'lock_version' => 1,
        ]);

    $this->travelTo($createdTime->addMinutes(35));
    $waitingResponse = $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticketNumber}/status", [
            'status' => 'waiting_for_customer',
            'lock_version' => 2,
        ]);
    $waitingTicket = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketNumber}");
    expect($waitingTicket->json('sla.warning_at'))->toBe($warningAt)
        ->and($waitingTicket->json('sla.due_at'))->toBe($dueAt);

    // 3. Resolve ticket
    $this->travelTo($createdTime->addMinutes(50));
    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticketNumber}/status", [
            'status' => 'resolved',
            'lock_version' => 3,
        ]);
    $resolvedTicket = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketNumber}");
    expect($resolvedTicket->json('sla.warning_at'))->toBe($warningAt)
        ->and($resolvedTicket->json('sla.due_at'))->toBe($dueAt);

    // 4. Reopen ticket
    $this->travelTo($createdTime->addMinutes(60));
    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticketNumber}/status", [
            'status' => 'open',
            'lock_version' => 4,
        ]);
    $reopenedTicket = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketNumber}");
    expect($reopenedTicket->json('sla.warning_at'))->toBe($warningAt)
        ->and($reopenedTicket->json('sla.due_at'))->toBe($dueAt);
});


test('a ticket reopened after its due time reads breached', function () {
    $createdTime = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');
    $this->travelTo($createdTime);

    $createResponse = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Critical ticket to be reopened',
            'description' => 'Due at 11:00.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);

    $ticketNumber = $createResponse->json('ticket_number');

    // Resolved at 10:00
    $this->travelTo($createdTime->addHour());
    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticketNumber}/status", [
            'status' => 'resolved',
            'lock_version' => 0,
        ]);

    // Customer reopens at 12:00 (past due 11:00)
    $this->travelTo($createdTime->addHours(3));
    $customerToken = $this->customer->createToken('customer')->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($customerToken)
        ->postJson("/api/v1/tickets/{$ticketNumber}/status", [
            'status' => 'open',
            'lock_version' => 1,
        ]);

    // Agent views reopened ticket
    $this->app['auth']->forgetGuards();
    $agentView = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketNumber}");
    $agentView->assertOk();
    expect($agentView->json('status'))->toBe('open')
        ->and($agentView->json('sla.status'))->toBe('breached');
});


test('sla status progresses from within to approaching to breached over time', function () {
    $createdTime = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');
    $this->travelTo($createdTime);

    $createResponse = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Time tracking ticket',
            'description' => 'Will check SLA status over time.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);

    $ticketNumber = $createResponse->json('ticket_number');

    // At 10:00 -> within
    $this->travelTo($createdTime->addHour());
    $res1000 = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketNumber}");
    expect($res1000->json('sla.status'))->toBe('within');

    // At 10:30 -> approaching
    $this->travelTo($createdTime->addHour()->addMinutes(30));
    $res1030 = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketNumber}");
    expect($res1030->json('sla.status'))->toBe('approaching');

    // At 11:00 -> breached
    $this->travelTo($createdTime->addHours(2));
    $res1100 = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketNumber}");
    expect($res1100->json('sla.status'))->toBe('breached');
});

test('critical ticket resolved before deadline is met and closed directly after deadline is missed', function () {
    $createdTime = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');

    // Ticket 1: resolved at 10:45 (before 11:00 deadline)
    $this->travelTo($createdTime);
    $res1 = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket to resolve in time',
            'description' => 'Will resolve at 10:45.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $num1 = $res1->json('ticket_number');

    $this->travelTo($createdTime->addHour()->addMinutes(45));
    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$num1}/status", [
            'status' => 'resolved',
            'lock_version' => 0,
        ]);

    $view1 = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$num1}");
    expect($view1->json('sla.status'))->toBe('met');

    // Ticket 2: closed directly from open at 11:30 (after 11:00 deadline)
    $this->travelTo($createdTime);
    $res2 = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket to close late without resolution',
            'description' => 'Will close at 11:30.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $num2 = $res2->json('ticket_number');

    $this->travelTo($createdTime->addHours(2)->addMinutes(30));
    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$num2}/status", [
            'status' => 'closed',
            'lock_version' => 0,
        ]);

    $view2 = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$num2}");
    expect($view2->json('sla.status'))->toBe('missed');
});


test('sla_status filter returns exactly matching tickets and combines with view and priority', function () {
    // Create one ticket of each SLA state
    $within = Ticket::factory()->slaWithin()->create(['priority' => 'low']);
    $approaching = Ticket::factory()->slaApproaching()->create(['priority' => 'high']);
    $breached = Ticket::factory()->slaBreached()->create(['priority' => 'critical']);
    $met = Ticket::factory()->slaMet()->create(['priority' => 'medium']);
    $missed = Ticket::factory()->slaMissed()->create(['priority' => 'low']);

    // 1. Filter by within
    $resWithin = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=all&sla_status=within');
    $resWithin->assertOk();
    $dataWithin = $resWithin->json('data');
    expect(count($dataWithin))->toBe(1)
        ->and($dataWithin[0]['ticket_number'])->toBe($within->ticket_number)
        ->and($dataWithin[0]['sla']['status'])->toBe('within');

    // 2. Filter by approaching
    $resApp = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=all&sla_status=approaching');
    $resApp->assertOk();
    $dataApp = $resApp->json('data');
    expect(count($dataApp))->toBe(1)
        ->and($dataApp[0]['ticket_number'])->toBe($approaching->ticket_number)
        ->and($dataApp[0]['sla']['status'])->toBe('approaching');

    // 3. Filter by breached
    $resBreached = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=all&sla_status=breached');
    $resBreached->assertOk();
    $dataBreached = $resBreached->json('data');
    expect(count($dataBreached))->toBe(1)
        ->and($dataBreached[0]['ticket_number'])->toBe($breached->ticket_number)
        ->and($dataBreached[0]['sla']['status'])->toBe('breached');

    // 4. Filter by met
    $resMet = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=all&sla_status=met');
    $resMet->assertOk();
    $dataMet = $resMet->json('data');
    expect(count($dataMet))->toBe(1)
        ->and($dataMet[0]['ticket_number'])->toBe($met->ticket_number)
        ->and($dataMet[0]['sla']['status'])->toBe('met');

    // 5. Filter by missed
    $resMissed = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=all&sla_status=missed');
    $resMissed->assertOk();
    $dataMissed = $resMissed->json('data');
    expect(count($dataMissed))->toBe(1)
        ->and($dataMissed[0]['ticket_number'])->toBe($missed->ticket_number)
        ->and($dataMissed[0]['sla']['status'])->toBe('missed');

    // Combines with view=active and priority=critical
    $combined = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=active&sla_status=breached&priority=critical');
    $combined->assertOk();
    expect(count($combined->json('data')))->toBe(1)
        ->and($combined->json('data.0.ticket_number'))->toBe($breached->ticket_number);

    $combinedMismatch = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=active&sla_status=breached&priority=low');
    $combinedMismatch->assertOk();
    expect(count($combinedMismatch->json('data')))->toBe(0);
});

test('customer sla_status filter is ignored and unknown value returns 422', function () {
    $cust = User::factory()->customer()->create();
    $custToken = $cust->createToken('cust')->plainTextToken;

    Ticket::factory()->slaWithin()->create(['customer_id' => $cust->id]);
    Ticket::factory()->slaBreached()->create(['customer_id' => $cust->id]);

    // Customer filters by breached -> ignored, returns both tickets
    $this->app['auth']->forgetGuards();
    $res = $this->withToken($custToken)->getJson('/api/v1/tickets?sla_status=breached');
    $res->assertOk();
    expect(count($res->json('data')))->toBe(2);

    // Unknown value returns 422 on sla_status
    $this->app['auth']->forgetGuards();
    $errRes = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?sla_status=invalid_status');
    $errRes->assertStatus(422)
        ->assertJsonValidationErrors(['sla_status']);
});

it('exposes escalated_at in sla payload for staff and null for unescalated ticket while customer sees no sla key', function () {
    $escalatedTime = CarbonImmutable::parse('2026-11-08 11:01:00', 'UTC');

    $escalatedTicket = Ticket::factory()->create([
        'customer_id' => $this->customer->id,
        'escalated_at' => $escalatedTime,
    ]);

    $unescalatedTicket = Ticket::factory()->create([
        'customer_id' => $this->customer->id,
        'escalated_at' => null,
    ]);

    // Agent detail view
    $this->app['auth']->forgetGuards();
    $detailRes = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$escalatedTicket->ticket_number}");
    $detailRes->assertOk();
    expect($detailRes->json('sla.escalated_at'))->toBe($escalatedTime->toISOString());

    $unescalatedDetailRes = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$unescalatedTicket->ticket_number}");
    $unescalatedDetailRes->assertOk();
    expect($unescalatedDetailRes->json('sla.escalated_at'))->toBeNull();

    // Agent list view
    $listRes = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=all');
    $listRes->assertOk();
    $listData = collect($listRes->json('data'))->keyBy('ticket_number');
    expect($listData->get($escalatedTicket->ticket_number)['sla']['escalated_at'])->toBe($escalatedTime->toISOString())
        ->and($listData->get($unescalatedTicket->ticket_number)['sla']['escalated_at'])->toBeNull();

    // Customer detail and list view
    $custToken = $this->customer->createToken('cust')->plainTextToken;
    $this->app['auth']->forgetGuards();
    $custDetailRes = $this->withToken($custToken)->getJson("/api/v1/tickets/{$escalatedTicket->ticket_number}");
    $custDetailRes->assertOk();
    expect($custDetailRes->json())->not->toHaveKey('sla');

    $custListRes = $this->withToken($custToken)->getJson('/api/v1/tickets');
    $custListRes->assertOk();
    foreach ($custListRes->json('data') as $item) {
        expect($item)->not->toHaveKey('sla');
    }
});

