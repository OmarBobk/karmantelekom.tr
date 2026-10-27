<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.'.config('database.default').'.database');

        if ($database !== 'dev_store_testing') {
            throw new RuntimeException("Refusing to run tests against the [{$database}] database. Tests must use [dev_store_testing].");
        }
    }
}
