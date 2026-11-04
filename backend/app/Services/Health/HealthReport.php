<?php

namespace App\Services\Health;

final readonly class HealthReport
{
    public function __construct(
        public bool $databaseReachable,
    ) {}

    public function isHealthy(): bool
    {
        return $this->databaseReachable;
    }

    /**
     * @return array{status: string, checks: array{database: string}}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->isHealthy() ? 'ok' : 'unavailable',
            'checks' => [
                'database' => $this->databaseReachable ? 'ok' : 'unavailable',
            ],
        ];
    }
}
