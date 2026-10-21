<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RuntimeException;
use Tests\Support\TestingDatabaseGuard;

/**
 * Pure unit tests: do not boot Laravel and do not connect to PostgreSQL.
 */
class TestingDatabaseGuardTest extends PhpUnitTestCase
{
    public function test_allows_testing_pgsql_rotor_testing(): void
    {
        TestingDatabaseGuard::assert('testing', 'pgsql', 'rotor_testing');

        $this->addToAssertionCount(1);
    }

    public function test_refuses_testing_against_rotor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(TestingDatabaseGuard::REFUSAL_MESSAGE);

        TestingDatabaseGuard::assert('testing', 'pgsql', 'rotor');
    }

    public function test_refuses_non_testing_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(TestingDatabaseGuard::REFUSAL_MESSAGE);

        TestingDatabaseGuard::assert('local', 'pgsql', 'rotor_testing');
    }

    public function test_subprocess_aborts_for_rotor_without_connecting(): void
    {
        $autoload = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $script = 'require '.var_export($autoload, true).';'
            .'Tests\\Support\\TestingDatabaseGuard::assert("testing", "pgsql", "rotor");';

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([PHP_BINARY, '-r', $script], $descriptor, $pipes);
        $this->assertIsResource($process);

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString(TestingDatabaseGuard::REFUSAL_MESSAGE, (string) $stderr);
    }
}
