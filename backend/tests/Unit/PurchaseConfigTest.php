<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PurchaseConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->clearTtlEnv();

        parent::tearDown();
    }

    public function test_checkout_session_ttl_minutes_is_clamped_to_stripe_allowed_range(): void
    {
        $cases = [
            'below minimum' => [1, 30],
            'within range' => [45, 45],
            'above maximum' => [2000, 1440],
        ];

        foreach ($cases as $label => [$configured, $expected]) {
            $this->setTtlEnv($configured);

            $config = require __DIR__.'/../../config/purchase.php';

            $this->assertSame($expected, $config['checkout_session_ttl_minutes'], "TTL mismatch for case: {$label}");
        }
    }

    public function test_checkout_session_ttl_minutes_defaults_to_30_when_unset(): void
    {
        $this->clearTtlEnv();

        $config = require __DIR__.'/../../config/purchase.php';

        $this->assertSame(30, $config['checkout_session_ttl_minutes']);
    }

    private function setTtlEnv(int $value): void
    {
        putenv("PURCHASE_CHECKOUT_SESSION_TTL_MINUTES={$value}");
        $_ENV['PURCHASE_CHECKOUT_SESSION_TTL_MINUTES'] = (string) $value;
        $_SERVER['PURCHASE_CHECKOUT_SESSION_TTL_MINUTES'] = (string) $value;
    }

    private function clearTtlEnv(): void
    {
        putenv('PURCHASE_CHECKOUT_SESSION_TTL_MINUTES');
        unset($_ENV['PURCHASE_CHECKOUT_SESSION_TTL_MINUTES'], $_SERVER['PURCHASE_CHECKOUT_SESSION_TTL_MINUTES']);
    }
}
