<?php

use App\Enums\TicketActivityType;
use App\Models\Ticket;
use App\Services\Sla\SlaEscalationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('concurrent escalation runs result in exactly one escalated entry', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl extension is required for concurrency test');
    }

    for ($round = 1; $round <= 10; $round++) {
        $now = now()->toImmutable();

        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'assigned_agent_id' => null,
            'lock_version' => 0,
            'sla_policy_minutes' => 120,
            'sla_started_at' => $now->subHours(5),
            'sla_warning_at' => $now->subHours(3),
            'sla_due_at' => $now->subHour(),
            'sla_warning_notified_at' => $now->subHours(3),
            'sla_breached_at' => null,
            'escalated_at' => null,
        ]);

        $statusFile1 = tempnam(sys_get_temp_dir(), 'esc_race_1_');
        $statusFile2 = tempnam(sys_get_temp_dir(), 'esc_race_2_');

        // Disconnect DB before fork so PDO connection is not shared across processes
        DB::disconnect();

        $startTime = microtime(true) + 0.08;

        $pid1 = pcntl_fork();
        if ($pid1 === -1) {
            $this->fail('Could not fork child 1');
        } elseif ($pid1 === 0) {
            while (microtime(true) < $startTime) {
                usleep(50);
            }
            DB::reconnect();
            /** @var SlaEscalationService $service */
            $service = app(SlaEscalationService::class);
            $result = $service->run(CarbonImmutable::now());
            file_put_contents($statusFile1, (string) $result->escalatedCount);
            posix_kill(getmypid(), SIGKILL);
        }

        $pid2 = pcntl_fork();
        if ($pid2 === -1) {
            $this->fail('Could not fork child 2');
        } elseif ($pid2 === 0) {
            while (microtime(true) < $startTime) {
                usleep(50);
            }
            DB::reconnect();
            /** @var SlaEscalationService $service */
            $service = app(SlaEscalationService::class);
            $result = $service->run(CarbonImmutable::now());
            file_put_contents($statusFile2, (string) $result->escalatedCount);
            posix_kill(getmypid(), SIGKILL);
        }

        // Parent
        pcntl_waitpid($pid1, $status1);
        pcntl_waitpid($pid2, $status2);

        DB::reconnect();

        $count1 = (int) file_get_contents($statusFile1);
        $count2 = (int) file_get_contents($statusFile2);
        @unlink($statusFile1);
        @unlink($statusFile2);

        $counts = [$count1, $count2];
        sort($counts);
        expect($counts)->toBe([0, 1], "Round {$round} failed with counts [{$count1}, {$count2}]");

        $ticket->refresh();
        expect($ticket->escalated_at)->not->toBeNull()
            ->and($ticket->sla_breached_at)->not->toBeNull();

        $escalatedActivities = $ticket->activities()->where('type', TicketActivityType::Escalated)->get();
        expect($escalatedActivities)->toHaveCount(1, "Round {$round} had multiple escalated entries");
    }
});

