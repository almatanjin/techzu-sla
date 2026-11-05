<?php

namespace App\Enums;

enum TicketMessageType: string
{
    case Reply = 'reply';
    case InternalNote = 'internal_note';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

