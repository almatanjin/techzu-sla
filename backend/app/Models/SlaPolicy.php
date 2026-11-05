<?php

namespace App\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['priority', 'resolution_minutes', 'warning_threshold_percent'])]
class SlaPolicy extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'resolution_minutes' => 'integer',
            'warning_threshold_percent' => 'integer',
        ];
    }
}

