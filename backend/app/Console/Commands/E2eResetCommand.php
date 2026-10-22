<?php

namespace App\Console\Commands;

use App\Support\E2eDatabaseGuard;
use Database\Seeders\E2eSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class E2eResetCommand extends Command
{
    protected $signature = 'e2e:reset';

    protected $description = 'Guard then migrate:fresh + DatabaseSeeder + E2eSeeder on rotor_e2e only';

    public function handle(): int
    {
        E2eEnvironment::load();

        $connection = (string) config('database.default');
        $configured = (string) config("database.connections.{$connection}.database");
        $current = (string) DB::selectOne('select current_database() as db')->db;

        try {
            E2eDatabaseGuard::assert((string) config('app.env'), $configured, $current);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('E2E_GUARD=PASS CURRENT_DATABASE='.$current);

        $migrate = Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
        if ($migrate !== 0) {
            $this->error(Artisan::output());

            return self::FAILURE;
        }
        $this->output->write(Artisan::output());

        $seed = Artisan::call('db:seed', [
            '--class' => E2eSeeder::class,
            '--force' => true,
        ]);
        if ($seed !== 0) {
            $this->error(Artisan::output());

            return self::FAILURE;
        }
        $this->output->write(Artisan::output());

        $this->info('E2E_RESET=PASS');

        return self::SUCCESS;
    }
}
