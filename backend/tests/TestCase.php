<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The tables that should not be truncated.
     *
     * @var array<int, string>
     */
    protected array $exceptTables = [
        'business_hours',
        'sla_policies',
    ];
}
