<?php

namespace App\Models;

use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ticket_number',
    'subject',
    'description',
    'priority',
    'status',
    'category_id',
    'customer_id',
    'created_by_id',
    'assigned_agent_id',
    'first_assigned_at',
    'resolved_at',
    'closed_at',
    'lock_version',
    'sla_policy_minutes',
    'sla_started_at',
    'sla_warning_at',
    'sla_due_at',
    'sla_warning_notified_at',
    'sla_breached_at',
    'escalated_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'first_assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'lock_version' => 'integer',
            'sla_policy_minutes' => 'integer',
            'sla_started_at' => 'datetime',
            'sla_warning_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_warning_notified_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /**
     * @return HasMany<TicketActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->whereNull('ticket_message_id');
    }

    public function slaStatus(?CarbonInterface $at = null): SlaStatus
    {
        $at = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        if (in_array($this->status, TicketStatus::active(), true)) {
            if ($at->lt($this->sla_warning_at)) {
                return SlaStatus::Within;
            }
            if ($at->lt($this->sla_due_at)) {
                return SlaStatus::Approaching;
            }

            return SlaStatus::Breached;
        }

        $finishedAt = $this->resolved_at ?? $this->closed_at;
        if ($finishedAt && $finishedAt->lte($this->sla_due_at)) {
            return SlaStatus::Met;
        }

        return SlaStatus::Missed;
    }

    /**
     * Scope a query to only include tickets visible to the given user.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isCustomer()) {
            return $query->where('customer_id', $user->id);
        }

        return $query;
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeWhereSlaStatus(Builder $query, SlaStatus $status, ?CarbonInterface $at = null): Builder
    {
        $at = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();
        $activeValues = array_map(fn ($s) => $s->value, TicketStatus::active());
        $closedValues = array_map(fn ($s) => $s->value, TicketStatus::closedView());

        return match ($status) {
            SlaStatus::Within => $query->whereIn('status', $activeValues)
                ->where('sla_warning_at', '>', $at),
            SlaStatus::Approaching => $query->whereIn('status', $activeValues)
                ->where('sla_warning_at', '<=', $at)
                ->where('sla_due_at', '>', $at),
            SlaStatus::Breached => $query->whereIn('status', $activeValues)
                ->where('sla_due_at', '<=', $at),
            SlaStatus::Met => $query->whereIn('status', $closedValues)
                ->whereRaw('COALESCE(resolved_at, closed_at) <= sla_due_at'),
            SlaStatus::Missed => $query->whereIn('status', $closedValues)
                ->whereRaw('COALESCE(resolved_at, closed_at) > sla_due_at'),
        };
    }
}
