<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->agent = User::factory()->agent()->create(['name' => 'Rafi']);
    $this->agentToken = $this->agent->createToken('rafi')->plainTextToken;

    $this->customer = User::factory()->customer()->create(['name' => 'Ana']);

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('accepts valid fixture attachments of every supported type', function (string $fixtureName) {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $file = fixtureUpload($fixtureName);

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => "Uploading {$fixtureName}",
            'attachments' => [$file],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('attachments.0.name', $fixtureName);
})->with([
    'screen.png',
    'invoice.pdf',
    'data.csv',
    'report.docx',
    'sheet.xlsx',
    'archive.zip',
]);

it('rejects setup.exe with 422 on attachments.0 naming the allowed types', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $file = fixtureUpload('setup.exe');

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Here is setup',
            'attachments' => [$file],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['attachments.0']);

    expect($response->json('errors')['attachments.0'][0])
        ->toBe('setup.exe must be one of these types: pdf, png, jpg, jpeg, gif, webp, txt, csv, log, docx, xlsx, zip.');
});

it('rejects photo.png (php script) and fake.pdf (plain text) with 422 on attachments.0', function (string $disguisedFixture) {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $file = fixtureUpload($disguisedFixture);

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Checking disguised file',
            'attachments' => [$file],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['attachments.0']);

    expect($response->json('errors')['attachments.0'][0])
        ->toBe("{$disguisedFixture} must be one of these types: pdf, png, jpg, jpeg, gif, webp, txt, csv, log, docx, xlsx, zip.");
})->with([
    'photo.png',
    'fake.pdf',
]);

it('enforces 10 MB limit: rejects 10752 KB file with custom message, accepts 10240 KB file', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    // 10,752 KB dump.zip
    $oversizedFile = UploadedFile::fake()->create('dump.zip', 10752, 'application/zip');

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Dump file',
            'attachments' => [$oversizedFile],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['attachments.0']);

    expect($response->json('errors')['attachments.0'][0])
        ->toBe('dump.zip must not be larger than 10 MB.');

    // 10,240 KB file is accepted
    $validFile = UploadedFile::fake()->create('valid.zip', 10240, 'application/zip');

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Valid limit file',
            'attachments' => [$validFile],
        ]);

    $response->assertStatus(201);
});

it('rejects more than 5 files with 422 on attachments reading You can attach up to 5 files.', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $files = [
        UploadedFile::fake()->create('f1.png', 10, 'image/png'),
        UploadedFile::fake()->create('f2.png', 10, 'image/png'),
        UploadedFile::fake()->create('f3.png', 10, 'image/png'),
        UploadedFile::fake()->create('f4.png', 10, 'image/png'),
        UploadedFile::fake()->create('f5.png', 10, 'image/png'),
        UploadedFile::fake()->create('f6.png', 10, 'image/png'),
    ];

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => '6 files attached',
            'attachments' => $files,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['attachments']);

    expect($response->json('errors.attachments.0'))
        ->toBe('You can attach up to 5 files.');
});

it('accepts an empty body when a valid file is attached and responds with 201 and empty body', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    $file = fixtureUpload('steps.txt');

    $response = $this->withToken($this->agentToken)
        ->post("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => '   ',
            'attachments' => [$file],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('body', '')
        ->assertJsonPath('attachments.0.name', 'steps.txt');
});
