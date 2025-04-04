<?php

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HeaderSanitizerService which sanitizes HTTP headers to prevent injection attacks
 */
class HeaderSanitizerServiceTest extends TestCase
{
    private const NORMAL_HEADER = 'Content-Type: application/json';
    private const MALICIOUS_NEWLINE_HEADER = "Content-Type: application/json\r\nX-Malicious: exploit";
    private const MALICIOUS_NEWLINE_HEADER_ALT = "Content-Type: application/json\nX-Malicious: exploit";
    private const VALID_TOKEN = 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ';
    private const SCRIPT_TOKEN = 'Bearer <script>alert(1)</script>';
    private const VALID_CSRF_TOKEN = 'a1b2c3d4-e5f6-g7h8-i9j0';
    private const SCRIPT_CSRF_TOKEN = 'a1b2c3d4-e5f6-<script>alert(1)</script>';
    private const VALID_ORIGIN = 'https://example.com';
    private const VALID_ORIGIN_WITH_PORT = 'https://example.com:8080';
    private const MALICIOUS_TOKEN_LINEBREAK = "Bearer abc123\r\nX-Custom: malicious";
    private const CSRF_TOKEN_LINEBREAK = "a1b2c3d4\r\nX-Custom: malicious";
    private const EVIL_ORIGIN = "https://evil.com\r\nX-Custom: malicious";
    private const MALFORMED_URL = 'not-a-url';
    private const DATA_URL = 'data:text/html,<script>alert(1)</script>';
    private const SCRIPT_IN_HOSTNAME = 'https://<script>alert(1)</script>.com';

    private HeaderSanitizerService $headerSanitizer;

    protected function setUp(): void
    {
        $this->headerSanitizer = new HeaderSanitizerService();
    }

    /**
     * Test basic header sanitization
     *
     * @return void
     */
    public function testSanitizeHeader(): void
    {
        // Test with normal header
        $result = $this->headerSanitizer->sanitizeHeader(self::NORMAL_HEADER);
        $this->assertEquals(self::NORMAL_HEADER, $result);

        // Test with header containing new lines (potential for HTTP header injection)
        $result = $this->headerSanitizer->sanitizeHeader(self::MALICIOUS_NEWLINE_HEADER);
        $this->assertEquals(self::NORMAL_HEADER, $result);

        // Test with header containing the other type of new line
        $result = $this->headerSanitizer->sanitizeHeader(self::MALICIOUS_NEWLINE_HEADER_ALT);
        $this->assertEquals(self::NORMAL_HEADER, $result);
    }

    /**
     * Test auth header sanitization
     *
     * @return void
     */
    public function testSanitizeAuthHeader(): void
    {
        // Test with valid token
        $result = $this->headerSanitizer->sanitizeAuthHeader(self::VALID_TOKEN);
        $this->assertEquals(self::VALID_TOKEN, $result);

        // Test with invalid characters
        $result = $this->headerSanitizer->sanitizeAuthHeader(self::SCRIPT_TOKEN);
        // The regex should remove all script tags and disallowed characters
        $this->assertEquals('Bearer alert1', $result);

        // Test with line breaks
        $result = $this->headerSanitizer->sanitizeAuthHeader(self::MALICIOUS_TOKEN_LINEBREAK);
        $this->assertEquals('Bearer abc123', $result);
    }

    /**
     * Test CSRF token sanitization
     *
     * @return void
     */
    public function testSanitizeCsrfToken(): void
    {
        // Test with valid CSRF token
        $result = $this->headerSanitizer->sanitizeCsrfToken(self::VALID_CSRF_TOKEN);
        $this->assertEquals(self::VALID_CSRF_TOKEN, $result);

        // Test with invalid characters
        $result = $this->headerSanitizer->sanitizeCsrfToken(self::SCRIPT_CSRF_TOKEN);
        // The regex should remove all script tags and disallowed characters
        $this->assertEquals('a1b2c3d4-e5f6-alert1', $result);

        // Test with line breaks
        $result = $this->headerSanitizer->sanitizeCsrfToken(self::CSRF_TOKEN_LINEBREAK);
        $this->assertEquals('a1b2c3d4', $result);
    }

    /**
     * Test origin sanitization
     *
     * @return void
     */
    public function testSanitizeOrigin(): void
    {
        // Test with valid origins
        $this->assertEquals(self::VALID_ORIGIN, $this->headerSanitizer->sanitizeOrigin(self::VALID_ORIGIN));

        $this->assertEquals(self::VALID_ORIGIN_WITH_PORT, $this->headerSanitizer->sanitizeOrigin(self::VALID_ORIGIN_WITH_PORT));

        // Test with malicious origin (line injection)
        // First-line extraction should remove the injection
        $this->assertEquals('https://evil.com', $this->headerSanitizer->sanitizeOrigin(self::EVIL_ORIGIN));

        // Test with malformed URL
        $this->assertEquals('', $this->headerSanitizer->sanitizeOrigin(self::MALFORMED_URL));

        // Test with data URL (which should be blocked)
        $this->assertEquals('', $this->headerSanitizer->sanitizeOrigin(self::DATA_URL));

        // Test with script in hostname
        $this->assertEquals('', $this->headerSanitizer->sanitizeOrigin(self::SCRIPT_IN_HOSTNAME));
    }
}
