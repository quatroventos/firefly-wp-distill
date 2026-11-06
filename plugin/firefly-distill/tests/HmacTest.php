<?php

namespace FireflyDistill\Tests;

use FireflyDistill\Security\Hmac;
use PHPUnit\Framework\TestCase;

final class HmacTest extends TestCase
{
    public function testSignIsDeterministic(): void
    {
        $a = Hmac::sign('secret', '1000', 'POST', '/api/v1/x', '{"a":1}');
        $b = Hmac::sign('secret', '1000', 'POST', '/api/v1/x', '{"a":1}');
        $this->assertSame($a, $b);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $a);
    }

    public function testVerifyWindow(): void
    {
        $ts = '1000';
        $sig = Hmac::sign('secret', $ts, 'GET', '/status', '');
        $this->assertTrue(Hmac::verify('secret', $ts, 'GET', '/status', '', $sig, 1000));
        $this->assertTrue(Hmac::verify('secret', $ts, 'GET', '/status', '', $sig, 1299));
        $this->assertFalse(Hmac::verify('secret', $ts, 'GET', '/status', '', $sig, 1301));
        $this->assertFalse(Hmac::verify('secret', $ts, 'POST', '/status', '', $sig, 1000));
        $this->assertFalse(Hmac::verify('wrong', $ts, 'GET', '/status', '', $sig, 1000));
    }

    public function testBodyChangesSignature(): void
    {
        $a = Hmac::sign('s', '1', 'POST', '/p', 'a');
        $b = Hmac::sign('s', '1', 'POST', '/p', 'b');
        $this->assertNotSame($a, $b);
    }
}
