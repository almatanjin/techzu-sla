<?php

namespace App\Enums;

enum SlaStatus: string
{
    case Within = 'within';
    case Approaching = 'approaching';
    case Breached = 'breached';
    case Met = 'met';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::Within => 'Within SLA',
            self::Approaching => 'Approaching',
            self::Breached => 'Breached',
            self::Met => 'Met',
            self::Missed => 'Missed',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

