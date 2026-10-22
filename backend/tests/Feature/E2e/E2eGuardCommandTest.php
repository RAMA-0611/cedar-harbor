<?php

namespace Tests\Feature\E2e;

use App\Support\E2eDatabaseGuard;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class E2eGuardCommandTest extends TestCase
{
    public function test_artisan_guard_aborts_on_phpunit_database(): void
    {
        $this->assertSame('rotor_testing', DB::selectOne('select current_database() as db')->db);

        $this->artisan('e2e:guard')
            ->expectsOutput(E2eDatabaseGuard::REFUSAL_MESSAGE)
            ->assertFailed();

        $this->assertSame('rotor_testing', DB::selectOne('select current_database() as db')->db);
    }
}
