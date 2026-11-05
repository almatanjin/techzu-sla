<?php

use App\Enums\TicketActivityType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CategorySeeder::class);

    $this->sara = User::factory()->admin()->create([
        'name' => 'Sara',
        'email' => 'sara@techzu-sla.test',
    ]);
    $this->saraToken = $this->sara->createToken('sara')->plainTextToken;

    $this->ana = User::factory()->customer()->create([
        'name' => 'Ana',
        'email' => 'ana@techzu-sla.test',
    ]);
    $this->anaToken = $this->ana->createToken('ana')->plainTextToken;

    $this->billingCategory = Category::query()->where('name', 'Billing')->firstOrFail();
});

it('verifies the Imported ticket scenario and activities', function () {
    $file = importFixtureUpload('sara-leg42.csv');

    $response = $this->withToken($this->saraToken)
        ->post('/api/v1/admin/imports', ['file' => $file]);

    $response->assertStatus(202);
    $importId = $response->json('id');

    $show = $this->withToken($this->saraToken)
        ->getJson("/api/v1/admin/imports/{$importId}");

    $show->assertStatus(200)
        ->assertJson([
            'status' => 'completed',
            'imported_rows' => 1,
        ]);

    $ticket = Ticket::query()->where('external_reference', 'LEG-42')->firstOrFail();

    // 1. TKT-2026- sequence number
    expect($ticket->ticket_number)->toMatch('/^TKT-2026-\d{6}$/')
        ->and($ticket->subject)->toBe('Imported ticket for Ana')
        ->and($ticket->description)->toBe('Details of LEG-42')
        ->and($ticket->priority->value)->toBe('high')
        ->and($ticket->status->value)->toBe('open')
        ->and($ticket->category_id)->toBe($this->billingCategory->id)
        ->and($ticket->customer_id)->toBe($this->ana->id)
        ->and($ticket->created_by_id)->toBe($this->sara->id)
        ->and($ticket->assigned_agent_id)->toBeNull()
        ->and($ticket->ticket_import_id)->toBe($importId)
        ->and($ticket->external_reference)->toBe('LEG-42')
        ->and($ticket->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 03:00:00');

    // Activities
    $activities = $ticket->activities()->get();
    expect($activities->count())->toBe(1);

    $activity = $activities->first();
    expect($activity->type->value)->toBe(TicketActivityType::Imported->value)
        ->and($activity->actor_id)->toBe($this->sara->id)
        ->and($activity->new_values)->toBe([
            'import_id' => $importId,
            'external_reference' => 'LEG-42',
        ]);

    // Check GET /api/v1/tickets/{number}/activities endpoint
    $actResponse = $this->withToken($this->saraToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");

    $actResponse->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.type', 'imported')
        ->assertJsonPath('0.actor.name', 'Sara')
        ->assertJsonPath('0.new_values.external_reference', 'LEG-42');
});

it('verifies an imported ticket and API-created ticket have matching fields in GET response', function () {
    $file = importFixtureUpload('sara-leg42.csv');

    $this->withToken($this->saraToken)
        ->post('/api/v1/admin/imports', ['file' => $file])
        ->assertStatus(202);

    $importedTicket = Ticket::query()->where('external_reference', 'LEG-42')->firstOrFail();

    // Create an API ticket with the same values by Sara for Ana
    $apiResponse = $this->withToken($this->saraToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Imported ticket for Ana',
            'description' => 'Details of LEG-42',
            'category_id' => $this->billingCategory->id,
            'customer_id' => $this->ana->id,
            'priority' => 'high',
        ]);
    $apiResponse->assertStatus(201);
    $apiTicketNumber = $apiResponse->json('ticket_number');

    // Fetch both via GET /api/v1/tickets/{number} as Sara (staff)
    $importedGet = $this->withToken($this->saraToken)
        ->getJson("/api/v1/tickets/{$importedTicket->ticket_number}")
        ->assertStatus(200)
        ->json();

    $apiGet = $this->withToken($this->saraToken)
        ->getJson("/api/v1/tickets/{$apiTicketNumber}")
        ->assertStatus(200)
        ->json();

    // Check matching schema / keys
    expect(array_keys($importedGet))->toEqual(array_keys($apiGet));

    // Exclude number, id, creator and timestamps
    $fieldsToCompare = [
        'subject',
        'description',
        'priority',
        'status',
        'category',
        'customer',
        'assigned_agent',
        'lock_version',
    ];

    foreach ($fieldsToCompare as $field) {
        expect($importedGet[$field])->toEqual($apiGet[$field]);
    }

    // SLA fields present
    expect($importedGet['sla'])->toHaveKeys(['status', 'policy_minutes', 'started_at', 'warning_at', 'due_at'])
        ->and($apiGet['sla'])->toHaveKeys(['status', 'policy_minutes', 'started_at', 'warning_at', 'due_at']);
});
