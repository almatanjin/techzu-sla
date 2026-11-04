<?php

namespace Database\Seeders;

use App\Enums\UserRole;
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
            $user->save();
        }
    }
}

