<?php

namespace App\Services\Health;

use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class HealthCheckService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly LoggerInterface $logger,
    ) {}

    public function check(): HealthReport
    {
        return new HealthReport(
            databaseReachable: $this->databaseIsReachable(),
        );
    }

    private function databaseIsReachable(): bool
    {
        try {
            $this->database->connection()->select('select 1');

            return true;
        } catch (Throwable $exception) {
            $this->logger->warning('Health check: database is unreachable.', ['exception' => $exception]);

            return false;
        }
    }
}
