<?php

use App\Services\Assignment\AgentCandidate;
use App\Services\Assignment\CategoryWorkloadStrategy;
use Carbon\CarbonImmutable;

test('category match wins over workload: Rafi handles Billing with 4 tickets and Mina only Technical with 0', function () {
    $strategy = new CategoryWorkloadStrategy();
    $mina = new AgentCandidate(id: 2, categoryIds: [2], activeTicketCount: 0);
    $rafi = new AgentCandidate(id: 1, categoryIds: [1], activeTicketCount: 4);

    expect($strategy->pick(1, [$mina, $rafi]))->toBe(1);
});

test('fallback when no agent handles category: lowest workload wins', function () {
    $strategy = new CategoryWorkloadStrategy();
    $rafi = new AgentCandidate(id: 1, categoryIds: [1], activeTicketCount: 2);
    $mina = new AgentCandidate(id: 2, categoryIds: [2], activeTicketCount: 1);

    expect($strategy->pick(3, [$rafi, $mina]))->toBe(2);
});

test('both handle category: lowest workload wins', function () {
    $strategy = new CategoryWorkloadStrategy();
    $rafi = new AgentCandidate(id: 1, categoryIds: [1], activeTicketCount: 3);
    $mina = new AgentCandidate(id: 2, categoryIds: [1], activeTicketCount: 1);

    expect($strategy->pick(1, [$rafi, $mina]))->toBe(2);
});

test('agent at maximum is skipped even with lower workload', function () {
    $strategy = new CategoryWorkloadStrategy();
    $rafi = new AgentCandidate(id: 1, categoryIds: [1], activeTicketCount: 2, maxOpenTickets: 2);
    $mina = new AgentCandidate(id: 2, categoryIds: [1], activeTicketCount: 5, maxOpenTickets: null);

    expect($strategy->pick(1, [$rafi, $mina]))->toBe(2);
});

test('returns null when everyone is at their maximum', function () {
    $strategy = new CategoryWorkloadStrategy();
    $rafi = new AgentCandidate(id: 1, categoryIds: [1], activeTicketCount: 2, maxOpenTickets: 2);
    $mina = new AgentCandidate(id: 2, categoryIds: [1], activeTicketCount: 3, maxOpenTickets: 3);

    expect($strategy->pick(1, [$rafi, $mina]))->toBeNull();
});

test('returns null when candidates list is empty', function () {
    $strategy = new CategoryWorkloadStrategy();

    expect($strategy->pick(1, []))->toBeNull();
});

test('tie break: oldest last assignment wins on equal workload', function () {
    $strategy = new CategoryWorkloadStrategy();
    $rafi = new AgentCandidate(
        id: 1,
        categoryIds: [1],
        activeTicketCount: 1,
        lastAssignedAt: CarbonImmutable::parse('2026-11-05 10:00:00')
    );
    $mina = new AgentCandidate(
        id: 2,
        categoryIds: [1],
        activeTicketCount: 1,
        lastAssignedAt: CarbonImmutable::parse('2026-11-05 09:00:00')
    );

    expect($strategy->pick(1, [$rafi, $mina]))->toBe(2);
});

test('tie break: never-assigned agent beats one assigned at 09:00', function () {
    $strategy = new CategoryWorkloadStrategy();
    $rafi = new AgentCandidate(
        id: 1,
        categoryIds: [1],
        activeTicketCount: 1,
        lastAssignedAt: CarbonImmutable::parse('2026-11-05 09:00:00')
    );
    $mina = new AgentCandidate(
        id: 2,
        categoryIds: [1],
        activeTicketCount: 1,
        lastAssignedAt: null
    );

    expect($strategy->pick(1, [$rafi, $mina]))->toBe(2);
});

test('tie break: equal workloads and times goes to lowest id', function () {
    $strategy = new CategoryWorkloadStrategy();
    $time = CarbonImmutable::parse('2026-11-05 09:00:00');
    $mina = new AgentCandidate(
        id: 2,
        categoryIds: [1],
        activeTicketCount: 1,
        lastAssignedAt: $time
    );
    $rafi = new AgentCandidate(
        id: 1,
        categoryIds: [1],
        activeTicketCount: 1,
        lastAssignedAt: $time
    );

    expect($strategy->pick(1, [$mina, $rafi]))->toBe(1);
});
