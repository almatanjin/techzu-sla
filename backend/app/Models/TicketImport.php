<?php

namespace App\Models;

use App\Enums\TicketImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'original_filename',
    'disk',
    'path',
    'status',
    'total_rows',
    'processed_rows',
    'imported_rows',
    'failed_rows',
    'duplicate_rows',
    'failure_message',
    'batch_id',
    'started_at',
    'finished_at',
])]
class TicketImport extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'imported_rows' => 'integer',
            'failed_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TicketImportError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(TicketImportError::class);
    }

    /**
     * @return HasMany<TicketImportChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(TicketImportChunk::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}

