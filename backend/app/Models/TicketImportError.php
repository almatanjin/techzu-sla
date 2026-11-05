<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_import_id',
    'line',
    'external_reference',
    'kind',
    'reasons',
    'raw_row',
])]
class TicketImportError extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'reasons' => 'array',
            'raw_row' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TicketImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(TicketImport::class, 'ticket_import_id');
    }
}

