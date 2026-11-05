<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;

it('creates exactly four categories and is idempotent when run twice', function () {
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(4);

    $names = Category::pluck('name')->all();
    expect($names)->toContain('General', 'Billing', 'Technical', 'Account');

    // Running the seeder a second time produces no duplicates
    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe(4);
});

it('seeds categories in production environment', function () {
    $this->app['env'] = 'production';

    $this->artisan('db:seed', [
        '--class' => CategorySeeder::class,
        '--force' => true,
    ]);

    expect(Category::count())->toBe(4);
});
