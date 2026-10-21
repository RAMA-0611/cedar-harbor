<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestingDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        TestingDatabaseGuard::assertFromApplication($this->app);
    }

    /**
     * Runs before RefreshDatabase migrates or drops schema.
     */
    protected function beforeRefreshingDatabase()
    {
        TestingDatabaseGuard::assertFromApplication($this->app);
    }
}
