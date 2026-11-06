<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->agent = User::factory()->agent()->create();
    $this->token = $this->agent->createToken('test')->plainTextToken;
});

/**
 * @return array<string, array<string, mixed>>
 */
function dailyByDate(TestResponse $response): array
{
    return collect($response->json('daily'))->keyBy('date')->all();
}

function businessTime(string $local, ?string $tz = null): CarbonImmutable
{
    return CarbonImmutable::parse($local, $tz ?? config('tickets.timezone'))->setTimezone('UTC');
}

test('daily lists 14 business-timezone days oldest first with gaps filled with 0', function () {
    $this->travelTo(businessTime('2026-11-06 10:00:00'));

    Ticket::factory()->open()->count(2)->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-01 12:00:00'),
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-06 09:00:00'),
    ]);

    $response = $this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk();

    $daily = $response->json('daily');
    expect($daily)->toHaveCount(14);
    expect(array_column($daily, 'date'))->toBe([
        '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30',
        '2026-10-31', '2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06',
    ]);

    $created = array_column($daily, 'created', 'date');
    expect($created['2026-11-01'])->toBe(2);
    expect($created['2026-11-06'])->toBe(1);
    expect(array_sum($created))->toBe(3);
});

test('daily created splits days at midnight in the business timezone', function () {
    $this->travelTo(businessTime('2026-11-06 10:00:00'));

    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-06 00:30:00'),
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-05 23:30:00'),
    ]);

    $daily = dailyByDate($this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk());

    expect($daily['2026-11-06']['created'])->toBe(1);
    expect($daily['2026-11-05']['created'])->toBe(1);
});

test('daily leaves out tickets created and resolved before the 14-day window', function () {
    $this->travelTo(businessTime('2026-11-06 10:00:00'));

    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-10-17 09:00:00'),
        'resolved_at' => businessTime('2026-10-17 11:00:00'),
    ]);
    // The last second before the window starts
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-10-23 23:59:59'),
    ]);

    $daily = $this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk()->json('daily');

    expect(array_sum(array_column($daily, 'created')))->toBe(0);
    expect(array_sum(array_column($daily, 'resolved')))->toBe(0);
});

test('daily buckets by local day in a timezone with a 45-minute offset', function () {
    config(['tickets.timezone' => 'Asia/Kathmandu']);
    $this->travelTo(businessTime('2026-11-06 10:00:00', 'Asia/Kathmandu'));

    // 23:50 local on 5 Nov is 18:05 UTC
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-05 23:50:00', 'Asia/Kathmandu'),
    ]);
    // 00:10 local on 6 Nov is 18:25 UTC
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-06 00:10:00', 'Asia/Kathmandu'),
    ]);

    $daily = dailyByDate($this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk());

    expect($daily['2026-11-05']['created'])->toBe(1);
    expect($daily['2026-11-06']['created'])->toBe(1);
});

test('daily counts resolved, sla_met and sla_missed on the day each ticket was finished', function () {
    $this->travelTo(businessTime('2026-11-06 10:00:00'));

    // Resolved before its due time
    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-05 08:00:00'),
        'sla_due_at' => businessTime('2026-11-05 12:00:00'),
        'resolved_at' => businessTime('2026-11-05 11:00:00'),
    ]);
    // Resolved after its due time, then closed the next day: finished on the resolution day
    Ticket::factory()->create([
        'category_id' => $this->category->id,
        'status' => TicketStatus::Closed,
        'created_at' => businessTime('2026-11-05 08:00:00'),
        'sla_due_at' => businessTime('2026-11-05 12:00:00'),
        'resolved_at' => businessTime('2026-11-05 13:00:00'),
        'closed_at' => businessTime('2026-11-06 09:00:00'),
    ]);
    // Resolved exactly at its due time: met
    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-03 08:00:00'),
        'sla_due_at' => businessTime('2026-11-04 12:00:00'),
        'resolved_at' => businessTime('2026-11-04 12:00:00'),
    ]);

    $daily = dailyByDate($this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk());

    expect($daily['2026-11-05'])->toMatchArray(['resolved' => 2, 'sla_met' => 1, 'sla_missed' => 1]);
    expect($daily['2026-11-04'])->toMatchArray(['resolved' => 1, 'sla_met' => 1, 'sla_missed' => 0]);
    expect($daily['2026-11-06'])->toMatchArray(['resolved' => 0, 'sla_met' => 0, 'sla_missed' => 0]);
});

test('daily counts a ticket closed without a resolution in sla_missed and not in resolved', function () {
    $this->travelTo(businessTime('2026-11-06 10:00:00'));

    Ticket::factory()->closed()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-04 08:00:00'),
        'sla_due_at' => businessTime('2026-11-05 09:00:00'),
        'resolved_at' => null,
        'closed_at' => businessTime('2026-11-05 10:00:00'),
    ]);

    $daily = dailyByDate($this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk());

    expect($daily['2026-11-05'])->toMatchArray(['resolved' => 0, 'sla_met' => 0, 'sla_missed' => 1]);
});

test('daily does not count a reopened ticket as finished', function () {
    $this->travelTo(businessTime('2026-11-06 10:00:00'));

    // Resolved on 4 Nov and reopened on 5 Nov, which cleared its resolution time
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => businessTime('2026-11-03 08:00:00'),
        'sla_due_at' => businessTime('2026-11-07 12:00:00'),
    ]);

    $daily = $this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk()->json('daily');

    expect(array_sum(array_column($daily, 'resolved')))->toBe(0);
    expect(array_sum(array_column($daily, 'sla_met')))->toBe(0);
    expect(array_sum(array_column($daily, 'sla_missed')))->toBe(0);
    expect(array_sum(array_column($daily, 'created')))->toBe(1);
});

test('daily resolved agrees with the resolved_today and resolved_last_7_days tiles', function () {
    $this->travelTo(businessTime('2026-11-06 10:00:00'));

    foreach (['2026-11-06 00:30:00', '2026-11-05 23:30:00', '2026-10-31 09:00:00', '2026-10-30 23:00:00', '2026-10-27 12:00:00'] as $resolvedAt) {
        Ticket::factory()->resolved()->create([
            'category_id' => $this->category->id,
            'created_at' => businessTime('2026-10-25 08:00:00'),
            'resolved_at' => businessTime($resolvedAt),
        ]);
    }

    $response = $this->withToken($this->token)->getJson('/api/v1/dashboard')->assertOk();
    $daily = $response->json('daily');
    $resolved = array_column($daily, 'resolved');

    $response->assertJsonPath('team.resolved_today', 1);
    $response->assertJsonPath('team.resolved_last_7_days', 3);
    expect(end($resolved))->toBe(1);
    expect(array_sum(array_slice($resolved, -7)))->toBe(3);
    expect(array_sum($resolved))->toBe(5);
});
