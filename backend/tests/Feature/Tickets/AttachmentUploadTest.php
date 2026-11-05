<?php

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->agent = User::factory()->agent()->create(['name' => 'Rafi']);
    $this->agentToken = $this->agent->createToken('rafi')->plainTextToken;

    $this->customer = User::factory()->customer()->create(['name' => 'Ana']);

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('allows an agent to post a reply with an attachment and stores it securely', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $file = fixtureUpload('steps.txt');
    $fileBytes = file_get_contents($file->getRealPath());
    $expectedSha256 = hash('sha256', $fileBytes);

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Here are the steps.',
            'attachments' => [$file],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('attachments.0.name', 'steps.txt')
        ->assertJsonPath('attachments.0.size_bytes', 2048)
        ->assertJsonPath('attachments.0.mime_type', 'text/plain');

    $attachmentId = $response->json('attachments.0.id');
    $attachment = Attachment::find($attachmentId);

    expect($attachment)->not->toBeNull()
        ->and($attachment->original_name)->toBe('steps.txt')
        ->and($attachment->size_bytes)->toBe(2048)
        ->and($attachment->mime_type)->toBe('text/plain')
        ->and($attachment->checksum_sha256)->toBe($expectedSha256)
        ->and($attachment->ticket_id)->toBe($ticket->id)
        ->and($attachment->path)->toStartWith("tickets/{$ticket->id}/")
        ->and($attachment->path)->not->toContain('steps');

    Storage::disk('local')->assertExists($attachment->path);
    expect(Storage::disk('local')->get($attachment->path))->toBe($fileBytes);

    // Activity entry has attachment_count 1
    $activity = TicketActivity::where('ticket_id', $ticket->id)
        ->where('type', 'replied')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->new_values['attachment_count'])->toBe(1);
});

