<?php

namespace Tests\Core\Core\Unit\Middlewares;

use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use demosplan\DemosPlanCoreBundle\Middlewares\TusCors;
use PHPUnit\Framework\TestCase;
use TusPhp\Request;
use TusPhp\Response;

/**
 * Tests for the TusCors middleware with focus on header sanitization
 */
class TusCorsTest extends TestCase
{
    private const STANDARD_ALLOW_HEADERS = 'Content-Type, X-Requested-With, Authorization';
    private const STANDARD_EXPOSE_HEADERS = 'Content-Type, X-Requested-With';
    private const MALICIOUS_HEADER = "Content-Type\r\nX-Malicious: exploit";

    private TusCors $tusCors;
    private HeaderSanitizerService $headerSanitizer;

    protected function setUp(): void
    {
        $this->headerSanitizer = new HeaderSanitizerService();
        $this->tusCors = new TusCors($this->headerSanitizer);
    }

    /**
     * Test that standard headers are properly processed
     */
    public function testStandardHeaders(): void
    {
        // Create request and response mocks
        $request = $this->createMock(Request::class);

        $headers = [
            'Access-Control-Allow-Headers' => self::STANDARD_ALLOW_HEADERS,
            'Access-Control-Expose-Headers' => self::STANDARD_EXPOSE_HEADERS,
        ];

        $response = $this->createMock(Response::class);
        $response->method('getHeaders')->willReturn($headers);

        // It should call replaceHeaders with the correct values
        $expectedHeaders = $headers;
        $expectedHeaders['Access-Control-Allow-Headers'] .= ', ' . Header::FILE_HASH . ', ' . Header::FILE_ID;
        $expectedHeaders['Access-Control-Expose-Headers'] .= ', ' . Header::FILE_HASH . ', ' . Header::FILE_ID;

        $response->expects($this->once())
            ->method('replaceHeaders')
            ->with($this->equalTo($expectedHeaders));

        $this->tusCors->handle($request, $response);
    }

    /**
     * Test that headers with malicious content are properly sanitized
     */
    public function testMaliciousHeaders(): void
    {
        // Create request and response mocks
        $request = $this->createMock(Request::class);

        $headers = [
            'Access-Control-Allow-Headers' => self::MALICIOUS_HEADER,
            'Access-Control-Expose-Headers' => self::MALICIOUS_HEADER,
        ];

        $response = $this->createMock(Response::class);
        $response->method('getHeaders')->willReturn($headers);

        // Get the sanitized header values
        $sanitizedAllowHeaders = $this->headerSanitizer->sanitizeHeader($headers['Access-Control-Allow-Headers']);
        $sanitizedExposeHeaders = $this->headerSanitizer->sanitizeHeader($headers['Access-Control-Expose-Headers']);
        $sanitizedFileHash = $this->headerSanitizer->sanitizeHeader(Header::FILE_HASH);
        $sanitizedFileId = $this->headerSanitizer->sanitizeHeader(Header::FILE_ID);

        // Expected headers after sanitization
        $expectedHeaders = [
            'Access-Control-Allow-Headers' => $sanitizedAllowHeaders . ', ' . $sanitizedFileHash . ', ' . $sanitizedFileId,
            'Access-Control-Expose-Headers' => $sanitizedExposeHeaders . ', ' . $sanitizedFileHash . ', ' . $sanitizedFileId,
        ];

        $response->expects($this->once())
            ->method('replaceHeaders')
            ->with($this->equalTo($expectedHeaders));

        $this->tusCors->handle($request, $response);
    }
}
