<?php

declare(strict_types=1);

namespace Tests\Feature\E2e;

use App\Console\Commands\E2eEnvironment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class E2eEnvironmentTest extends TestCase
{
    private string $e2eEnvPath;

    private ?string $e2eEnvBackup = null;

    private string $originalDefault;

    private string $originalAppEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->e2eEnvPath = base_path('.env.e2e');
        $this->originalDefault = (string) config('database.default');
        $this->originalAppEnv = (string) config('app.env');

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

        // Dotenv::createMutable mutates process env; restore PHPUnit isolation for the next test.
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
        DB::purge('sqlite');
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        parent::tearDown();
    }

    public function test_load_switches_default_from_sqlite_to_pgsql_from_env_e2e(): void
    {
        config(['database.default' => 'sqlite']);
        $this->assertSame('sqlite', config('database.default'));

        $creds = $this->e2eDbCredentials();
        $this->writeE2eEnv([
            'APP_ENV' => 'e2e',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://127.0.0.1:8011',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $creds['DB_HOST'],
            'DB_PORT' => $creds['DB_PORT'],
            'DB_DATABASE' => 'rotor_e2e',
            'DB_USERNAME' => $creds['DB_USERNAME'],
            'DB_PASSWORD' => $creds['DB_PASSWORD'],
        ]);

        E2eEnvironment::load();

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('e2e', config('app.env'));
        $this->assertSame('rotor_e2e', config('database.connections.pgsql.database'));
        $this->assertSame('rotor_e2e', DB::selectOne('select current_database() as db')->db);
    }

    /**
     * @return array{DB_HOST: string, DB_PORT: string, DB_USERNAME: string, DB_PASSWORD: string}
     */
    private function e2eDbCredentials(): array
    {
        $source = $this->e2eEnvBackup ?? '';
        $map = [
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '5432',
            'DB_USERNAME' => 'rotor',
            'DB_PASSWORD' => '',
        ];
        foreach (preg_split("/\r\n|\n|\r/", $source) as $line) {
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            if (array_key_exists($key, $map)) {
                $map[$key] = trim($value, " \t\"'");
            }
        }

        return $map;
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
