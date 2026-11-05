<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The default categories.
     *
     * @var array<int, string>
     */
    public const DEFAULT_CATEGORIES = [
        'General',
        'Billing',
        'Technical',
        'Account',
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_CATEGORIES as $name) {
            Category::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}

