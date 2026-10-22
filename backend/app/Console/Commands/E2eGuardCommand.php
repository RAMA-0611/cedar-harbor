<?php

namespace App\Console\Commands;

use App\Support\E2eDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class E2eGuardCommand extends Command
{
    protected $signature = 'e2e:guard {--e2e : Load backend/.env.e2e before checking}';

    protected $description = 'Abort unless the current connection is PostgreSQL rotor_e2e in APP_ENV=e2e';

    public function handle(): int
    {
        if ($this->option('e2e')) {
            E2eEnvironment::load();
        }

        $connection = (string) config('database.default');
        $configured = (string) config("database.connections.{$connection}.database");
        $current = (string) DB::selectOne('select current_database() as db')->db;

        try {
            E2eDatabaseGuard::assert((string) config('app.env'), $configured, $current);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('E2E_GUARD=PASS');
        $this->line('APP_ENV='.(string) config('app.env'));
        $this->line('DB_DATABASE='.$configured);
        $this->line('CURRENT_DATABASE='.$current);

        return self::SUCCESS;
    }
}
