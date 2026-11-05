<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->ana = User::factory()->customer()->create(['name' => 'Ana']);
    $this->anaToken = $this->ana->createToken('ana')->plainTextToken;
    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('answers 413 with a JSON message when request payload exceeds post_max_size', function () {
    Route::post('/api/v1/test-oversized', function () {
        throw new PostTooLargeException();
    });

    $response = $this->withToken($this->anaToken)
        ->postJson('/api/v1/test-oversized');

    $response->assertStatus(413)
        ->assertJsonStructure(['message']);

    expect($response->json('message'))->not->toBeEmpty();
});

