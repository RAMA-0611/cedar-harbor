<?php

namespace App\Support;

use RuntimeException;

final class E2eDatabaseGuard
{
    public const ALLOWED_ENVIRONMENT = 'e2e';

    public const ALLOWED_DATABASE = 'rotor_e2e';

    public const REFUSAL_MESSAGE = 'Refusing E2E reset outside rotor_e2e.';

    public static function assert(?string $environment, ?string $configuredDatabase, ?string $currentDatabase): void
    {
        if ($environment !== self::ALLOWED_ENVIRONMENT
            || $configuredDatabase !== self::ALLOWED_DATABASE
            || $currentDatabase !== self::ALLOWED_DATABASE) {
            throw new RuntimeException(self::REFUSAL_MESSAGE);
        }
    }
}
