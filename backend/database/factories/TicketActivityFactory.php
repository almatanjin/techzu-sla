<?php

namespace Database\Factories;

use App\Enums\TicketActivityType;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketActivity>
 */
class TicketActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'actor_id' => User::factory()->agent(),
            'type' => TicketActivityType::Created,
            'old_values' => null,
            'new_values' => null,
            'created_at' => now(),
        ];
    }
}

