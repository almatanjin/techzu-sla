<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForCustomer = 'waiting_for_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::WaitingForCustomer => 'Waiting for Customer',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [
            self::Open,
            self::InProgress,
            self::WaitingForCustomer,
        ];
    }

    /**
     * @return list<self>
     */
    public static function closedView(): array
    {
        return [
            self::Resolved,
            self::Closed,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

