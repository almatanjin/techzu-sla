<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoTicketSeeder;
use Database\Seeders\DemoUserSeeder;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->seed(DemoUserSeeder::class);
});

it('seeds approximately 40 tickets with activities in non-production environment', function () {
    $this->seed(DemoTicketSeeder::class);

    $count = Ticket::count();
    expect($count)->toBe(40);

    // Each ticket has at least a 'created' activity
    expect(TicketActivity::where('type', 'created')->count())->toBe(40);

    // Non-open tickets have status_changed activity
    $nonOpenCount = Ticket::where('status', '!=', TicketStatus::Open->value)->count();
    expect(TicketActivity::where('type', 'status_changed')->count())->toBe($nonOpenCount);

    // Mixed priorities and statuses exist
    expect(Ticket::where('status', TicketStatus::Open->value)->count())->toBeGreaterThan(0);
    expect(Ticket::where('status', TicketStatus::InProgress->value)->count())->toBeGreaterThan(0);
    expect(Ticket::where('status', TicketStatus::Resolved->value)->count())->toBeGreaterThan(0);
    expect(Ticket::where('status', TicketStatus::Closed->value)->count())->toBeGreaterThan(0);

    expect(Ticket::where('priority', TicketPriority::Low->value)->count())->toBeGreaterThan(0);
    expect(Ticket::where('priority', TicketPriority::Medium->value)->count())->toBeGreaterThan(0);
    expect(Ticket::where('priority', TicketPriority::High->value)->count())->toBeGreaterThan(0);
    expect(Ticket::where('priority', TicketPriority::Critical->value)->count())->toBeGreaterThan(0);
});

it('does not create tickets when running in production environment', function () {
    $this->app['env'] = 'production';

    $this->artisan('db:seed', ['--class' => DemoTicketSeeder::class, '--force' => true]);

    expect(Ticket::count())->toBe(0);
    expect(TicketActivity::count())->toBe(0);
});

it('is skipped when tickets already exist', function () {
    $customer = User::where('role', 'customer')->first();
    $category = Category::first();

    Ticket::factory()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
        'created_by_id' => $customer->id,
    ]);

    expect(Ticket::count())->toBe(1);

    $this->seed(DemoTicketSeeder::class);

    // Count remains 1
    expect(Ticket::count())->toBe(1);
});
