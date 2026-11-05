<?php

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Tickets\TicketActivityLogger;
use App\Services\Tickets\TicketMessageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->agent = User::factory()->agent()->create(['name' => 'Rafi']);
    $this->agentToken = $this->agent->createToken('rafi')->plainTextToken;

    $this->customer = User::factory()->customer()->create(['name' => 'Ana']);

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('stores no message, no attachment row, and no file when any file is invalid', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $validFile = fixtureUpload('steps.txt');
    $invalidFile = fixtureUpload('setup.exe');

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Here are files',
            'attachments' => [$validFile, $invalidFile],
        ]);

    $response->assertStatus(422);

    expect(TicketMessage::count())->toBe(0)
        ->and(Attachment::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('cleans up written files on disk when transaction fails', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    // Force failure inside transaction by mocking TicketActivityLogger to throw
    $mockLogger = Mockery::mock(TicketActivityLogger::class);
    $mockLogger->shouldReceive('log')->andThrow(new \RuntimeException('Database failure simulation'));
    $this->app->instance(TicketActivityLogger::class, $mockLogger);

    $file = fixtureUpload('steps.txt');

    try {
        $this->withToken($this->agentToken)
            ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
                'body' => 'Testing failure cleanup',
                'attachments' => [$file],
            ]);
    } catch (\Throwable $e) {
        // Handled or uncaught depending on middleware
    }

    expect(TicketMessage::count())->toBe(0)
        ->and(Attachment::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('sanitizes ../../etc/passwd.txt to passwd.txt and excludes path traversal names from disk path', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $file = UploadedFile::fake()->createWithContent('../../etc/passwd.txt', "root:x:0:0:root:/root:/bin/bash\n");

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Uploading passwd',
            'attachments' => [$file],
        ]);

    $response->assertStatus(201);

    $attachment = Attachment::first();
    expect($attachment)->not->toBeNull()
        ->and($attachment->original_name)->toBe('passwd.txt')
        ->and($attachment->path)->not->toContain('etc')
        ->and($attachment->path)->not->toContain('passwd')
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();
});

