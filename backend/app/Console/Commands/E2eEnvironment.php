<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class E2eEnvironment
{
    public static function load(): void
    {
        $file = base_path('.env.e2e');
        if (! is_file($file)) {
            throw new RuntimeException('Missing backend/.env.e2e (local, gitignored).');
        }

        Dotenv::createMutable(base_path(), '.env.e2e')->load();

        $connection = (string) config('database.default', 'pgsql');
        config([
            'app.env' => env('APP_ENV'),
            'app.debug' => filter_var(env('APP_DEBUG'), FILTER_VALIDATE_BOOL),
            'app.url' => env('APP_URL'),
            "database.connections.{$connection}.host" => env('DB_HOST', '127.0.0.1'),
            "database.connections.{$connection}.port" => env('DB_PORT', '5432'),
            "database.connections.{$connection}.database" => env('DB_DATABASE'),
            "database.connections.{$connection}.username" => env('DB_USERNAME'),
            "database.connections.{$connection}.password" => env('DB_PASSWORD'),
        ]);

        DB::purge($connection);
        DB::reconnect($connection);
    }
}
