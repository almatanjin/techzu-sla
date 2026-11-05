<?php

namespace App\Imports;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;

class ImportRowValidator
{
    /**
     * @param  array<string, string|null>  $mapped
     * @param  array<int, mixed>  $rawRow
     * @param  array<string, mixed>  $customersByEmail
     * @param  array<string, mixed>  $categoriesByName
     * @return array{row: ?ImportRow, reasons: list<string>}
     */
    public function validate(
        int $line,
        array $mapped,
        array $rawRow,
        array $customersByEmail,
        array $categoriesByName,
        CarbonImmutable $now,
    ): array {
        $reasons = [];

        // UTF-8 check
        foreach ($rawRow as $val) {
            if (is_string($val) && ! mb_check_encoding($val, 'UTF-8')) {
                return [
                    'row' => null,
                    'reasons' => ['Row is not valid UTF-8.'],
                ];
            }
        }

        // External reference
        $externalReference = trim((string) ($mapped['external_reference'] ?? ''));
        if ($externalReference === '') {
            $reasons[] = 'external_reference: Required.';
        } elseif (mb_strlen($externalReference) > 100) {
            $reasons[] = 'external_reference: Must not exceed 100 characters.';
        }

        // Customer email
        $customerEmail = strtolower(trim((string) ($mapped['customer_email'] ?? '')));
        if ($customerEmail === '' || ! isset($customersByEmail[$customerEmail])) {
            $reasons[] = 'customer_email: No customer account has this email.';
        }

        // Subject
        $subject = trim((string) ($mapped['subject'] ?? ''));
        if ($subject === '') {
            $reasons[] = 'subject: Required.';
        } elseif (mb_strlen($subject) > 200) {
            $reasons[] = 'subject: Must not exceed 200 characters.';
        }

        // Description
        $description = trim((string) ($mapped['description'] ?? ''));
        if ($description === '') {
            $reasons[] = 'description: Required.';
        } elseif (mb_strlen($description) > 10000) {
            $reasons[] = 'description: Must not exceed 10000 characters.';
        }

        // Priority
        $priorityRaw = strtolower(trim((string) ($mapped['priority'] ?? '')));
        $priority = TicketPriority::tryFrom($priorityRaw);
        if ($priority === null) {
            $reasons[] = 'priority: Must be one of low, medium, high, critical.';
        }

        // Status
        $statusRaw = strtolower(trim((string) ($mapped['status'] ?? '')));
        $status = TicketStatus::tryFrom($statusRaw);
        if ($status === null && $statusRaw === 'waiting') {
            $status = TicketStatus::WaitingForCustomer;
        }
        if ($status === null) {
            $reasons[] = 'status: Must be one of '.implode(', ', TicketStatus::values()).'.';
        }

        // Category
        $categoryRaw = strtolower(trim((string) ($mapped['category'] ?? '')));
        if ($categoryRaw === '' || ! isset($categoriesByName[$categoryRaw])) {
            $reasons[] = 'category: Category does not exist.';
        }

        // Time parsing
        $businessTz = config('tickets.timezone', 'Asia/Dhaka');
        $createdAtRaw = trim((string) ($mapped['created_at'] ?? ''));
        $resolvedAtRaw = trim((string) ($mapped['resolved_at'] ?? ''));
        $closedAtRaw = trim((string) ($mapped['closed_at'] ?? ''));

        $createdAt = $now;
        if ($createdAtRaw !== '') {
            $parsed = $this->parseTimestamp($createdAtRaw, $businessTz);
            if ($parsed === null) {
                $reasons[] = 'created_at: Invalid date format.';
            } else {
                $createdAt = $parsed;
                if ($createdAt->gt($now)) {
                    $reasons[] = 'created_at: Must not be in the future.';
                }
            }
        }

        $resolvedAt = null;
        if ($resolvedAtRaw !== '') {
            $resolvedAt = $this->parseTimestamp($resolvedAtRaw, $businessTz);
            if ($resolvedAt === null) {
                $reasons[] = 'resolved_at: Invalid date format.';
            }
        }

        $closedAt = null;
        if ($closedAtRaw !== '') {
            $closedAt = $this->parseTimestamp($closedAtRaw, $businessTz);
            if ($closedAt === null) {
                $reasons[] = 'closed_at: Invalid date format.';
            }
        }

        if ($status !== null) {
            $isActive = in_array($status, TicketStatus::active(), true);

            if ($isActive) {
                if ($resolvedAtRaw !== '') {
                    $reasons[] = 'resolved_at: Must not be provided for active tickets.';
                }
                if ($closedAtRaw !== '') {
                    $reasons[] = 'closed_at: Must not be provided for active tickets.';
                }
            } elseif ($status === TicketStatus::Resolved) {
                if ($resolvedAtRaw === '') {
                    $reasons[] = 'resolved_at: Required when status is resolved.';
                } else {
                    if ($resolvedAt !== null) {
                        if ($resolvedAt->gt($now)) {
                            $reasons[] = 'resolved_at: Must not be in the future.';
                        }
                        if ($resolvedAt->lt($createdAt)) {
                            $reasons[] = 'resolved_at: Must not be earlier than created_at.';
                        }
                    }
                }
            } elseif ($status === TicketStatus::Closed) {
                if ($closedAtRaw === '') {
                    $reasons[] = 'closed_at: Required when status is closed.';
                } else {
                    if ($closedAt !== null) {
                        if ($closedAt->gt($now)) {
                            $reasons[] = 'closed_at: Must not be in the future.';
                        }
                        if ($closedAt->lt($createdAt)) {
                            $reasons[] = 'closed_at: Must not be earlier than created_at.';
                        }
                    }
                }
            }
        }

        if (! empty($reasons)) {
            return [
                'row' => null,
                'reasons' => $reasons,
            ];
        }

        return [
            'row' => new ImportRow(
                line: $line,
                externalReference: $externalReference,
                customerEmail: $customerEmail,
                subject: $subject,
                description: $description,
                priority: $priority,
                status: $status,
                categoryName: $categoriesByName[$categoryRaw]->name,
                createdAt: $createdAt,
                resolvedAt: $resolvedAt,
                closedAt: $closedAt,
                rawRow: $rawRow,
            ),
            'reasons' => [],
        ];
    }

    private function parseTimestamp(string $value, string $businessTimezone): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // If string contains timezone offset or Z
        if (preg_match('/[Zz]|[+-]\d{2}:?\d{2}$/', $value)) {
            try {
                return CarbonImmutable::parse($value)->setTimezone('UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return CarbonImmutable::parse($value, $businessTimezone)->setTimezone('UTC');
        } catch (\Throwable) {
            return null;
        }
    }
}
