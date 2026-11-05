<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const SHARED_PASSWORD = 'Password123';

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $agentSettings = [
            'agent1@techzu-sla.test' => [
                'max_open_tickets' => 15,
                'is_available' => true,
                'categories' => ['Billing', 'Account'],
            ],
            'agent2@techzu-sla.test' => [
                'max_open_tickets' => 15,
                'is_available' => true,
                'categories' => ['Technical'],
            ],
            'agent3@techzu-sla.test' => [
                'max_open_tickets' => null,
                'is_available' => false,
                'categories' => ['General', 'Technical'],
            ],
        ];

        $categoriesByName = Category::all()->keyBy('name');

        $accounts = [
            [
                'name' => 'Demo Administrator',
                'email' => 'admin@techzu-sla.test',
                'role' => UserRole::Admin,
            ],
            [
                'name' => 'Support Agent 1',
                'email' => 'agent1@techzu-sla.test',
                'role' => UserRole::Agent,
            ],
            [
                'name' => 'Support Agent 2',
                'email' => 'agent2@techzu-sla.test',
                'role' => UserRole::Agent,
            ],
            [
                'name' => 'Support Agent 3',
                'email' => 'agent3@techzu-sla.test',
                'role' => UserRole::Agent,
            ],
            [
                'name' => 'Customer 1',
                'email' => 'customer1@techzu-sla.test',
                'role' => UserRole::Customer,
            ],
            [
                'name' => 'Customer 2',
                'email' => 'customer2@techzu-sla.test',
                'role' => UserRole::Customer,
            ],
            [
                'name' => 'Customer 3',
                'email' => 'customer3@techzu-sla.test',
                'role' => UserRole::Customer,
            ],
            [
                'name' => 'Customer 4',
                'email' => 'customer4@techzu-sla.test',
                'role' => UserRole::Customer,
            ],
            [
                'name' => 'Customer 5',
                'email' => 'customer5@techzu-sla.test',
                'role' => UserRole::Customer,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrNew(['email' => $account['email']]);
            $user->name = $account['name'];
            $user->role = $account['role'];
            $user->password = self::SHARED_PASSWORD;
            $user->is_active = true;

            if (isset($agentSettings[$account['email']])) {
                $settings = $agentSettings[$account['email']];
                $user->max_open_tickets = $settings['max_open_tickets'];
                $user->is_available = $settings['is_available'];
            }

            $user->save();

            if (isset($agentSettings[$account['email']])) {
                $categoryIds = collect($agentSettings[$account['email']]['categories'])
                    ->map(fn ($name) => $categoriesByName->get($name)?->id)
                    ->filter()
                    ->all();
                $user->categories()->sync($categoryIds);
            }
        }
    }
}

