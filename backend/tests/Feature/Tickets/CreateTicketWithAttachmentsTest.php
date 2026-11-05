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

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('allows customer Ana to create a ticket with attachments and lists them in 201 and GET responses', function () {
    $file1 = fixtureUpload('screen.png');
    $file2 = fixtureUpload('invoice.pdf');

    $response = $this->withToken($this->anaToken)
        ->post('/api/v1/tickets', [
            'subject' => 'Billing issue with screenshots',
            'description' => 'Attached are screen and invoice.',
            'category_id' => $this->category->id,
            'attachments' => [$file1, $file2],
        ]);

    $response->assertStatus(201)
        ->assertJsonCount(2, 'attachments')
        ->assertJsonPath('attachments.0.name', 'screen.png')
        ->assertJsonPath('attachments.0.mime_type', 'image/png')
        ->assertJsonPath('attachments.1.name', 'invoice.pdf')
        ->assertJsonPath('attachments.1.mime_type', 'application/pdf');

    expect($response->json('attachments.0'))->toHaveKeys(['id', 'name', 'mime_type', 'size_bytes', 'created_at'])
        ->and($response->json('attachments.0'))->not->toHaveKeys(['disk', 'path']);

    $ticketNumber = $response->json('ticket_number');

    $getResponse = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticketNumber}");

    $getResponse->assertOk()
        ->assertJsonCount(2, 'attachments')
        ->assertJsonPath('attachments.0.name', 'screen.png')
        ->assertJsonPath('attachments.1.name', 'invoice.pdf');

    $attachments = Attachment::where('ticket_id', $response->json('id'))->get();
    expect($attachments)->toHaveCount(2);
    foreach ($attachments as $att) {
        expect($att->ticket_message_id)->toBeNull();
        Storage::disk('local')->assertExists($att->path);
    }
});

