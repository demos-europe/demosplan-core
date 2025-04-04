<?php

namespace Tests\Core\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HeaderSanitizerService which sanitizes HTTP headers to prevent injection attacks
 */
class HeaderSanitizerServiceTest extends TestCase
{
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
        $result = $this->headerSanitizer->sanitizeHeader('Content-Type: application/json');
        $this->assertEquals('Content-Type: application/json', $result);

        // Test with header containing new lines (potential for HTTP header injection)
        $result = $this->headerSanitizer->sanitizeHeader("Content-Type: application/json\r\nX-Malicious: exploit");
        $this->assertEquals('Content-Type: application/json', $result);
        
        // Test with header containing the other type of new line
        $result = $this->headerSanitizer->sanitizeHeader("Content-Type: application/json\nX-Malicious: exploit");
        $this->assertEquals('Content-Type: application/json', $result);
    }

    /**
     * Test auth header sanitization
     * 
     * @return void
     */
    public function testSanitizeAuthHeader(): void
    {
        // Test with valid token
        $validToken = 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ';
        $result = $this->headerSanitizer->sanitizeAuthHeader($validToken);
        $this->assertEquals($validToken, $result);

        // Test with invalid characters
        $invalidToken = 'Bearer <script>alert(1)</script>';
        $result = $this->headerSanitizer->sanitizeAuthHeader($invalidToken);
        // The regex should remove all script tags and disallowed characters
        $this->assertEquals('Bearer alert1', $result);

        // Test with line breaks
        $tokenWithLineBreak = "Bearer abc123\r\nX-Custom: malicious";
        $result = $this->headerSanitizer->sanitizeAuthHeader($tokenWithLineBreak);
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
        $validToken = 'a1b2c3d4-e5f6-g7h8-i9j0';
        $result = $this->headerSanitizer->sanitizeCsrfToken($validToken);
        $this->assertEquals('a1b2c3d4-e5f6-g7h8-i9j0', $result);

        // Test with invalid characters
        $invalidToken = 'a1b2c3d4-e5f6-<script>alert(1)</script>';
        $result = $this->headerSanitizer->sanitizeCsrfToken($invalidToken);
        // The regex should remove all script tags and disallowed characters
        $this->assertEquals('a1b2c3d4-e5f6-alert1', $result);

        // Test with line breaks
        $tokenWithLineBreak = "a1b2c3d4\r\nX-Custom: malicious";
        $result = $this->headerSanitizer->sanitizeCsrfToken($tokenWithLineBreak);
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
        $validOrigin = 'https://example.com';
        $this->assertEquals($validOrigin, $this->headerSanitizer->sanitizeOrigin($validOrigin));
        
        $validOriginWithPort = 'https://example.com:8080';
        $this->assertEquals($validOriginWithPort, $this->headerSanitizer->sanitizeOrigin($validOriginWithPort));

        // Test with malicious origin (line injection)
        $invalidOrigin = "https://evil.com\r\nX-Custom: malicious";
        // First-line extraction should remove the injection
        $this->assertEquals('https://evil.com', $this->headerSanitizer->sanitizeOrigin($invalidOrigin));
        
        // Test with malformed URL
        $malformedUrl = 'not-a-url';
        $this->assertEquals('', $this->headerSanitizer->sanitizeOrigin($malformedUrl));
        
        // Test with data URL (which should be blocked)
        $dataUrl = 'data:text/html,<script>alert(1)</script>';
        $this->assertEquals('', $this->headerSanitizer->sanitizeOrigin($dataUrl));
        
        // Test with script in hostname
        $invalidOriginWithScript = 'https://<script>alert(1)</script>.com';
        $this->assertEquals('', $this->headerSanitizer->sanitizeOrigin($invalidOriginWithScript));
    }
}