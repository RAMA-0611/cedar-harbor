<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Temporary demo: proves CI blocks build/deploy when a test fails. Do not merge. */
class CiFailureProofTest extends TestCase
{
    public function test_deliberate_ci_failure_demo(): void
    {
        $this->fail('CI failure proof for Entrega 4 sustentación — intentional.');
    }
}
