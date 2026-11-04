<?php

use Illuminate\Support\Facades\Route;

it('serves endpoints only under the /api/v1 prefix', function () {
    $this->getJson('/api/health')->assertNotFound();
});

it('answers unknown API routes with JSON even without an Accept header', function () {
    $this->get('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

it('hides exception details in API errors when debug is off', function () {
    config(['app.debug' => false]);
    Route::get('api/v1/testing/explode', fn () => throw new RuntimeException('internal secret detail'));

    $this->get('/api/v1/testing/explode')
        ->assertInternalServerError()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'Server Error']);
});
