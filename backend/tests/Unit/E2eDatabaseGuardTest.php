<?php

namespace Tests\Unit;

use App\Support\E2eDatabaseGuard;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;

/**
 * Pure unit tests: do not connect to PostgreSQL.
 */
class E2eDatabaseGuardTest extends PhpUnitTestCase
{
    public function test_allows_e2e_rotor_e2e(): void
    {
        E2eDatabaseGuard::assert('e2e', 'rotor_e2e', 'rotor_e2e');

        $this->addToAssertionCount(1);
    }

    public function test_refuses_rotor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(E2eDatabaseGuard::REFUSAL_MESSAGE);

        E2eDatabaseGuard::assert('e2e', 'rotor', 'rotor');
    }

    public function test_refuses_rotor_testing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(E2eDatabaseGuard::REFUSAL_MESSAGE);

        E2eDatabaseGuard::assert('e2e', 'rotor_testing', 'rotor_testing');
    }

    public function test_refuses_non_e2e_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(E2eDatabaseGuard::REFUSAL_MESSAGE);

        E2eDatabaseGuard::assert('local', 'rotor_e2e', 'rotor_e2e');
    }

    public function test_refuses_configured_mismatch(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(E2eDatabaseGuard::REFUSAL_MESSAGE);

        E2eDatabaseGuard::assert('e2e', 'rotor_e2e', 'rotor');
    }
}
