<?php

namespace FireflyDistill\Tests;

use FireflyDistill\Hosts\HostValidator;
use PHPUnit\Framework\TestCase;

final class HostValidatorTest extends TestCase
{
    public function testNormalizeStripsSchemePortAndPath(): void
    {
        $this->assertSame('lp.exemplo.com.br', HostValidator::normalize('https://LP.Exemplo.com.br:443/foo'));
    }

    public function testValidHost(): void
    {
        $this->assertTrue(HostValidator::isValid('incavel.com.br'));
        $this->assertTrue(HostValidator::isValid('lp-consorcio.exemplo.com.br'));
    }

    public function testRejectsInvalid(): void
    {
        $this->assertFalse(HostValidator::isValid(''));
        $this->assertFalse(HostValidator::isValid('https://x.com'));
        $this->assertFalse(HostValidator::isValid('x.com/path'));
        $this->assertFalse(HostValidator::isValid('evil..com'));
        $this->assertFalse(HostValidator::isValid('-bad.com'));
        $this->assertFalse(HostValidator::isValid('bad_host.com'));
        $this->assertFalse(HostValidator::isValid('x.com:80'));
    }

    public function testNormalizePath(): void
    {
        $this->assertSame('/', HostValidator::normalizePath(''));
        $this->assertSame('/obrigado/', HostValidator::normalizePath('obrigado'));
        $this->assertSame('/a/b/', HostValidator::normalizePath('/a//b'));
    }
}
