<?php

namespace Tests\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final class TestingDatabaseGuard
{
    public const ALLOWED_DATABASE = 'rotor_testing';

    public const REFUSAL_MESSAGE = 'Refusing to run tests against non-testing database.';

    public static function assert(?string $environment, ?string $connection, ?string $database): void
    {
        if ($environment !== 'testing'
            || $connection !== 'pgsql'
            || $database !== self::ALLOWED_DATABASE) {
            throw new RuntimeException(self::REFUSAL_MESSAGE);
        }
    }

    public static function assertFromApplication(Application $app): void
    {
        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        self::assert(
            $app->environment(),
            $connection,
            $database,
        );
    }
}
