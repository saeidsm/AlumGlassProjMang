<?php

use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testEscapeHtmlEscapesAngleBrackets(): void
    {
        $this->assertSame('&lt;script&gt;', escapeHtml('<script>'));
    }

    public function testEscapeHtmlEscapesQuotes(): void
    {
        $this->assertSame('&quot;x&quot;', escapeHtml('"x"'));
        $this->assertSame('&#039;y&#039;', escapeHtml("'y'"));
    }

    public function testEscapeHtmlHandlesNull(): void
    {
        $this->assertSame('', escapeHtml(null));
    }

    public function testCsrfTokenIsGenerated(): void
    {
        $token = generateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testCsrfTokenIsStableAcrossCalls(): void
    {
        $a = generateCsrfToken();
        $b = generateCsrfToken();
        $this->assertSame($a, $b);
    }

    public function testCsrfTokenIsHexAndLongEnough(): void
    {
        $token = generateCsrfToken();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testVerifyCsrfTokenAcceptsCorrectToken(): void
    {
        $token = generateCsrfToken();
        $this->assertTrue(verifyCsrfToken($token));
    }

    public function testVerifyCsrfTokenRejectsWrongToken(): void
    {
        generateCsrfToken();
        $this->assertFalse(verifyCsrfToken('not-the-right-token'));
    }

    public function testVerifyCsrfTokenRejectsEmptyToken(): void
    {
        generateCsrfToken();
        $this->assertFalse(verifyCsrfToken(''));
    }

    public function testCsrfFieldRendersHiddenInputWithEscapedToken(): void
    {
        $_SESSION = [];
        $field = csrfField();
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="' . $_SESSION['csrf_token'] . '"', $field);
    }
}
