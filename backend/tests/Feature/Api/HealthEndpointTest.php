<?php

use Illuminate\Support\Facades\DB;

it('reports ok when the database is reachable', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok'],
        ]);
});

it('reports unavailable without leaking details when the database is unreachable', function () {
    config([
        'database.connections.mysql.host' => 'db-host.invalid',
        'database.connections.mysql.options' => [PDO::ATTR_TIMEOUT => 1],
    ]);
    DB::purge('mysql');

    $this->getJson('/api/v1/health')
        ->assertServiceUnavailable()
        ->assertExactJson([
            'status' => 'unavailable',
            'checks' => ['database' => 'unavailable'],
        ])
        ->assertDontSee('db-host.invalid')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee(config('database.connections.mysql.password'));
});
