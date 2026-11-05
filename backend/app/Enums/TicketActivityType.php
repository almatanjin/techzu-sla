<?php

namespace App\Enums;

enum TicketActivityType: string
{
    case Created = 'created';
    case Imported = 'imported';
    case SubjectChanged = 'subject_changed';
    case DescriptionChanged = 'description_changed';
    case PriorityChanged = 'priority_changed';
    case CategoryChanged = 'category_changed';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case Claimed = 'claimed';
    case Unassigned = 'unassigned';
    case Replied = 'replied';
    case InternalNoteAdded = 'internal_note_added';
    case SlaWarning = 'sla_warning';
    case Escalated = 'escalated';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

