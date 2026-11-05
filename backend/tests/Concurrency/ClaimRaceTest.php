<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('concurrent claims result in exactly one winner and one conflict', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl extension is required for concurrency test');
    }

    $admin = User::factory()->admin()->create();
    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    for ($round = 1; $round <= 10; $round++) {
        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'assigned_agent_id' => null,
            'lock_version' => 0,
        ]);

        $agent1 = User::factory()->agent()->create(['name' => "Agent One Round {$round}"]);
        $agent2 = User::factory()->agent()->create(['name' => "Agent Two Round {$round}"]);

        $token1 = $agent1->createToken('agent-1')->plainTextToken;
        $token2 = $agent2->createToken('agent-2')->plainTextToken;

        $statusFile1 = tempnam(sys_get_temp_dir(), 'race_1_');
        $statusFile2 = tempnam(sys_get_temp_dir(), 'race_2_');

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
            auth('sanctum')->forgetUser();
            $response = $this->withHeader('Authorization', "Bearer {$token1}")
                ->postJson("/api/v1/tickets/{$ticket->ticket_number}/claim");
            file_put_contents($statusFile1, (string) $response->status());
            posix_kill(getmypid(), SIGKILL);
        }

        $pid2 = pcntl_fork();
        if ($pid2 === -1) {
            $this->fail('Could not fork child 2');
        } elseif ($pid2 === 0) {
            while (microtime(true) < $startTime) {
                usleep(50);
            }
            auth('sanctum')->forgetUser();
            $response = $this->withHeader('Authorization', "Bearer {$token2}")
                ->postJson("/api/v1/tickets/{$ticket->ticket_number}/claim");
            file_put_contents($statusFile2, (string) $response->status());
            posix_kill(getmypid(), SIGKILL);
        }

        // Parent
        pcntl_waitpid($pid1, $status1);
        pcntl_waitpid($pid2, $status2);

        DB::reconnect();
        auth('sanctum')->forgetUser();

        $code1 = (int) file_get_contents($statusFile1);
        $code2 = (int) file_get_contents($statusFile2);
        @unlink($statusFile1);
        @unlink($statusFile2);

        $statuses = [$code1, $code2];
        sort($statuses);
        expect($statuses)->toBe([200, 409], "Round {$round} failed with statuses [{$code1}, {$code2}]");

        // Verify ticket via API
        $ticketResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/tickets/{$ticket->ticket_number}")
            ->assertOk();

        $ticketData = $ticketResponse->json();
        expect($ticketData['assigned_agent'])->not->toBeNull()
            ->and(in_array($ticketData['assigned_agent']['id'], [$agent1->id, $agent2->id], true))->toBeTrue()
            ->and($ticketData['lock_version'])->toBe(1);

        // Verify activities
        $activitiesResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
            ->assertOk();

        $claimedActivities = collect($activitiesResponse->json())->where('type', 'claimed');
        expect($claimedActivities)->toHaveCount(1);
    }
});
