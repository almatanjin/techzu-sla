<?php

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->ana = User::factory()->customer()->create(['name' => 'Ana']);
    $this->anaToken = $this->ana->createToken('ana')->plainTextToken;

    $this->rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $this->rafiToken = $this->rafi->createToken('rafi')->plainTextToken;

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('allows customer Ana to download steps.txt from an agent reply with expected headers and bytes', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $file = fixtureUpload('steps.txt');
    $fileBytes = file_get_contents($file->getRealPath());

    // Agent replies with steps.txt
    $postResponse = $this->withToken($this->rafiToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Here are the steps for you.',
            'attachments' => [$file],
        ]);
    $postResponse->assertStatus(201);
    $attachmentId = $postResponse->json('attachments.0.id');

    // Ana downloads the attachment
    $this->app['auth']->forgetGuards();
    $downloadResponse = $this->withToken($this->anaToken)
        ->get("/api/v1/attachments/{$attachmentId}/download");

    $downloadResponse->assertOk();
    expect($downloadResponse->streamedContent())->toBe($fileBytes)
        ->and($downloadResponse->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($downloadResponse->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($downloadResponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and($downloadResponse->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($downloadResponse->headers->get('Content-Disposition'))->toContain('filename=steps.txt');
});

it('enforces visibility matrix across role x file location x endpoint', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin']);
    $adminToken = $admin->createToken('admin')->plainTextToken;

    $ben = User::factory()->customer()->create(['name' => 'Ben']);
    $benToken = $ben->createToken('ben')->plainTextToken;

    // 1. Ana creates ticket with ticket-level file
    $ticketFile = fixtureUpload('screen.png');
    $createRes = $this->withToken($this->anaToken)
        ->post('/api/v1/tickets', [
            'subject' => 'Ticket with file',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'attachments' => [$ticketFile],
        ])->assertStatus(201);

    $ticketNumber = $createRes->json('ticket_number');
    $ticketAttachmentId = $createRes->json('attachments.0.id');

    // 2. Agent replies with reply file
    $replyFile = fixtureUpload('invoice.pdf');
    $this->app['auth']->forgetGuards();
    $replyRes = $this->withToken($this->rafiToken)
        ->post("/api/v1/tickets/{$ticketNumber}/messages", [
            'body' => 'Public reply with invoice',
            'attachments' => [$replyFile],
        ])->assertStatus(201);
    $replyAttachmentId = $replyRes->json('attachments.0.id');

    // 3. Agent adds internal note with note file
    $noteFile = fixtureUpload('steps.txt');
    $this->app['auth']->forgetGuards();
    $noteRes = $this->withToken($this->rafiToken)
        ->post("/api/v1/tickets/{$ticketNumber}/messages", [
            'type' => 'internal_note',
            'body' => 'Staff note with steps',
            'attachments' => [$noteFile],
        ])->assertStatus(201);
    $noteAttachmentId = $noteRes->json('attachments.0.id');

    // --- Role: Ana (customer) ---
    $this->app['auth']->forgetGuards();
    // Messages list: sees reply file, omits note
    $anaMessages = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticketNumber}/messages")
        ->assertOk()
        ->json('data');
    expect(count($anaMessages))->toBe(1)
        ->and($anaMessages[0]['attachments'][0]['id'])->toBe($replyAttachmentId);

    // Ticket detail: sees ticket file
    $this->app['auth']->forgetGuards();
    $anaDetail = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticketNumber}")
        ->assertOk()
        ->json();
    expect($anaDetail['attachments'][0]['id'])->toBe($ticketAttachmentId);

    // Downloads: can download ticket file and reply file; 404 for note file
    $this->app['auth']->forgetGuards();
    $this->withToken($this->anaToken)->get("/api/v1/attachments/{$ticketAttachmentId}/download")->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withToken($this->anaToken)->get("/api/v1/attachments/{$replyAttachmentId}/download")->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withToken($this->anaToken)->get("/api/v1/attachments/{$noteAttachmentId}/download")->assertNotFound();

    // --- Role: Ben (other customer) ---
    $this->app['auth']->forgetGuards();
    $this->withToken($benToken)->getJson("/api/v1/tickets/{$ticketNumber}/messages")->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->withToken($benToken)->getJson("/api/v1/tickets/{$ticketNumber}")->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->withToken($benToken)->get("/api/v1/attachments/{$ticketAttachmentId}/download")->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->withToken($benToken)->get("/api/v1/attachments/{$replyAttachmentId}/download")->assertNotFound();
    $this->app['auth']->forgetGuards();
    $this->withToken($benToken)->get("/api/v1/attachments/{$noteAttachmentId}/download")->assertNotFound();

    // --- Role: Agent Rafi & Admin ---
    foreach ([$this->rafiToken, $adminToken] as $staffToken) {
        $this->app['auth']->forgetGuards();
        // Messages list: sees both reply and note files
        $staffMessages = $this->withToken($staffToken)
            ->getJson("/api/v1/tickets/{$ticketNumber}/messages")
            ->assertOk()
            ->json('data');
        expect(count($staffMessages))->toBe(2);

        // Ticket detail: sees ticket file
        $this->app['auth']->forgetGuards();
        $this->withToken($staffToken)->getJson("/api/v1/tickets/{$ticketNumber}")->assertOk();

        // Downloads: can download all 3 files
        $this->app['auth']->forgetGuards();
        $this->withToken($staffToken)->get("/api/v1/attachments/{$ticketAttachmentId}/download")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($staffToken)->get("/api/v1/attachments/{$replyAttachmentId}/download")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($staffToken)->get("/api/v1/attachments/{$noteAttachmentId}/download")->assertOk();
    }

    // --- Unauthenticated ---
    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->getJson("/api/v1/attachments/{$ticketAttachmentId}/download")->assertUnauthorized();
});
