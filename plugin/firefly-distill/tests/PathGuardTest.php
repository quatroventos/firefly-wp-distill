<?php

namespace FireflyDistill\Tests;

use FireflyDistill\Security\PathGuard;
use PHPUnit\Framework\TestCase;

final class PathGuardTest extends TestCase
{
    public function testRejectsTraversalAndPhp(): void
    {
        $this->assertFalse(PathGuard::isSafeRelative('../etc/passwd'));
        $this->assertFalse(PathGuard::isSafeRelative('/etc/passwd'));
        $this->assertFalse(PathGuard::isSafeRelative('shell.php'));
        $this->assertFalse(PathGuard::isSafeRelative('x.phtml'));
        $this->assertFalse(PathGuard::isSafeRelative('.htaccess'));
    }

    public function testAllowsHtmlCss(): void
    {
        $this->assertTrue(PathGuard::isSafeRelative('index.html'));
        $this->assertTrue(PathGuard::isSafeRelative('sobre/index.html'));
        $this->assertTrue(PathGuard::isSafeRelative('assets/site.css'));
        $this->assertTrue(PathGuard::isSafeRelative('robots.txt'));
        $this->assertTrue(PathGuard::isSafeRelative('sitemap.xml'));
        $this->assertTrue(PathGuard::isSafeRelative('llm.txt'));
    }
}
