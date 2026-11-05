<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_import_id',
    'chunk_index',
    'created_at',
])]
class TicketImportChunk extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_import_id' => 'integer',
            'chunk_index' => 'integer',
            'created_at' => 'datetime',
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

