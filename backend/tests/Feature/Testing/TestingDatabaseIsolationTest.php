<?php

namespace Tests\Feature\Testing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_database_is_rotor_testing(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('rotor_testing', config('database.connections.pgsql.database'));
        $this->assertSame('rotor_testing', DB::selectOne('select current_database() as name')->name);
    }
}
