<?php

declare(strict_types=1);

namespace Tests\Feature\E2e;

use App\Console\Commands\E2eEnvironment;
use Tests\TestCase;

/**
 * Config-level regression for the CI sqlite trap.
 * Uses applyFromEnvFile() so PHPUnit on rotor_testing does not need rotor_e2e.
 */
class E2eEnvironmentTest extends TestCase
{
    private string $e2eEnvPath;

    private ?string $e2eEnvBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->e2eEnvPath = base_path('.env.e2e');
        if (is_file($this->e2eEnvPath)) {
            $this->e2eEnvBackup = (string) file_get_contents($this->e2eEnvPath);
        }
    }

    protected function tearDown(): void
    {
        if ($this->e2eEnvBackup !== null) {
            file_put_contents($this->e2eEnvPath, $this->e2eEnvBackup);
        } elseif (is_file($this->e2eEnvPath)) {
            unlink($this->e2eEnvPath);
        }

        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        putenv('DB_CONNECTION=pgsql');
        $_ENV['DB_CONNECTION'] = 'pgsql';
        $_SERVER['DB_CONNECTION'] = 'pgsql';
        putenv('DB_DATABASE=rotor_testing');
        $_ENV['DB_DATABASE'] = 'rotor_testing';
        $_SERVER['DB_DATABASE'] = 'rotor_testing';

        config([
            'app.env' => 'testing',
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'rotor_testing',
        ]);

        parent::tearDown();
    }

    public function test_apply_switches_default_from_sqlite_to_pgsql_from_env_e2e(): void
    {
        config(['database.default' => 'sqlite']);
        $this->assertSame('sqlite', config('database.default'));

        $this->writeE2eEnv([
            'APP_ENV' => 'e2e',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://127.0.0.1:8011',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'rotor_e2e',
            'DB_USERNAME' => 'ci_user',
            'DB_PASSWORD' => 'ci_password',
        ]);

        E2eEnvironment::applyFromEnvFile();

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('e2e', config('app.env'));
        $this->assertSame('rotor_e2e', config('database.connections.pgsql.database'));
        $this->assertSame('ci_user', config('database.connections.pgsql.username'));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function writeE2eEnv(array $values): void
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = $key.'='.$value;
        }
        file_put_contents($this->e2eEnvPath, implode("\n", $lines)."\n");
    }
}
