<?php

namespace FireflyDistill\Tests;

use FireflyDistill\Crawl\BypassToken;
use PHPUnit\Framework\TestCase;

final class BypassTokenTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('FFD_HMAC_SECRET')) {
            define('FFD_HMAC_SECRET', 'unit-secret');
        }
    }

    public function testRoundTrip(): void
    {
        $now = 1_700_000_000;
        $token = BypassToken::issue('lp.example.test', '/obrigado', 120, $now);
        $this->assertTrue(BypassToken::validate('lp.example.test', '/obrigado', $token['ff_sig'], $token['ff_exp'], $now));
        $this->assertFalse(BypassToken::validate('outro.host', '/obrigado', $token['ff_sig'], $token['ff_exp'], $now));
        $this->assertFalse(BypassToken::validate('lp.example.test', '/', $token['ff_sig'], $token['ff_exp'], $now));
    }

    public function testExpired(): void
    {
        $now = 1_700_000_000;
        $token = BypassToken::issue('lp.example.test', '/', 60, $now);
        $this->assertFalse(BypassToken::validate('lp.example.test', '/', $token['ff_sig'], $token['ff_exp'], $now + 61));
    }
}
