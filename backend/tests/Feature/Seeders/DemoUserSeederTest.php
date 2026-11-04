<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;

it('creates exactly nine demo accounts and is idempotent when run twice', function () {
    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(9);

    $adminCount = User::where('role', 'admin')->count();
    $agentCount = User::where('role', 'agent')->count();
    $customerCount = User::where('role', 'customer')->count();

    expect($adminCount)->toBe(1)
        ->and($agentCount)->toBe(3)
        ->and($customerCount)->toBe(5);

    // Running the seeder a second time produces no duplicate accounts
    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(9);
});

it('does not create any accounts when running in production environment', function () {
    $this->app['env'] = 'production';

    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(0);
});
