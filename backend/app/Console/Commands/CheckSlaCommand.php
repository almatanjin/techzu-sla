<?php

namespace App\Console\Commands;

use App\Services\Sla\SlaEscalationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check SLA deadlines to warn and escalate active tickets';

    /**
     * Execute the console command.
     */
    public function handle(SlaEscalationService $service): int
    {
        $now = now()->toImmutable();
        $result = $service->run($now);

        $this->info("Checked SLA: {$result->warnedCount} warned, {$result->escalatedCount} escalated.");

        return self::SUCCESS;
    }
}

