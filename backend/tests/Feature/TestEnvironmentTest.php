<?php

use Illuminate\Support\Facades\DB;

it('runs against the dedicated test database', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(config('database.connections.mysql.host'))->toBe('mysql-test')
        ->and(DB::connection()->getDatabaseName())->toBe('testing');
});
