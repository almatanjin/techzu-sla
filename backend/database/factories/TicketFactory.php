<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) now(config('tickets.timezone', 'Asia/Dhaka'))->format('Y');

        return [
            'ticket_number' => sprintf('TKT-%d-%06d', $year, fake()->unique()->numberBetween(1, 999999)),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'priority' => TicketPriority::Medium,
            'status' => TicketStatus::Open,
            'category_id' => Category::factory(),
            'customer_id' => User::factory()->customer(),
            'created_by_id' => fn (array $attributes) => $attributes['customer_id'],
            'assigned_agent_id' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'lock_version' => 0,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Open,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::InProgress,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function waitingForCustomer(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::WaitingForCustomer,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
            'closed_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn () => [
            'priority' => $priority,
        ]);
    }
}

