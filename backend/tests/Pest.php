<?php

use Tests\TestCase;

/*
| Feature tests boot the full Laravel application. Unit tests stay plain
| PHPUnit test cases so they run without the framework.
*/

pest()->extend(TestCase::class)->in('Feature');
