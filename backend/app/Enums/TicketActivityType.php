<?php

namespace App\Enums;

enum TicketActivityType: string
{
    case Created = 'created';
    case SubjectChanged = 'subject_changed';
    case DescriptionChanged = 'description_changed';
    case PriorityChanged = 'priority_changed';
    case CategoryChanged = 'category_changed';
    case StatusChanged = 'status_changed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

