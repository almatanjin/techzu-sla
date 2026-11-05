<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the full Laravel application. Unit tests stay plain
| PHPUnit test cases so they run without the framework.
*/

pest()->extend(TestCase::class)->use(LazilyRefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->use(DatabaseTruncation::class)->in('Concurrency');
pest()->extend(TestCase::class)->use(DatabaseTruncation::class)->in('Search');
pest()->extend(TestCase::class)->use(DatabaseTruncation::class)->in('Import');

function fixtureUpload(string $filename): \Illuminate\Http\UploadedFile
{
    $path = __DIR__.'/Fixtures/attachments/'.$filename;

    return new \Illuminate\Http\UploadedFile($path, $filename, null, null, true);
}

function importFixtureUpload(string $filename): \Illuminate\Http\UploadedFile
{
    $path = __DIR__.'/Fixtures/imports/'.$filename;

    return new \Illuminate\Http\UploadedFile($path, $filename, 'text/csv', null, true);
}

