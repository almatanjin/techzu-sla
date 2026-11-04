<?php

it('allows preflight requests from the frontend origin with an Authorization header', function () {
    $response = $this->call('OPTIONS', '/api/v1/health', server: [
        'HTTP_ORIGIN' => 'http://localhost:5173',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
    ]);

    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    expect(strtolower($response->headers->get('Access-Control-Allow-Headers', '')))
        ->toContain('authorization');
});

it('does not grant access to other origins', function () {
    $response = $this->withHeaders(['Origin' => 'https://evil.example'])
        ->getJson('/api/v1/health');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.example')
        ->not->toBe('*');
});
