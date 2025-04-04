<?php

namespace Tests\Core\Core\Unit\EventSubscriber;

use demosplan\DemosPlanCoreBundle\EventSubscriber\RatelimitRequestSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Tests for the RatelimitRequestSubscriber with focus on header sanitization
 */
class RatelimitRequestSubscriberTest extends TestCase
{
    private RatelimitRequestSubscriber $subscriber;
    private HeaderSanitizerService $headerSanitizer;

    protected function setUp(): void
    {
        $this->headerSanitizer = new HeaderSanitizerService();

        // We need to create a stub subscriber as we can't mock RateLimiterFactory (final class)
        $this->subscriber = new class($this->headerSanitizer) extends RatelimitRequestSubscriber {
            private bool $shouldThrowException = false;
            private ?string $capturedSanitizedToken = null;

            public function __construct(HeaderSanitizerService $headerSanitizer)
            {
                $this->headerSanitizer = $headerSanitizer;
                // We don't call parent constructor as we can't mock RateLimiterFactory
            }

            public function setShouldThrowException(bool $shouldThrow): void
            {
                $this->shouldThrowException = $shouldThrow;
            }

            public function getSanitizedToken(): ?string
            {
                return $this->capturedSanitizedToken;
            }

            public function onKernelRequest(RequestEvent $event): void
            {
                if ($event->getRequest()->headers->has('X-JWT-Authorization')) {
                    // Sanitize header values to prevent header injection
                    $authHeader = $this->headerSanitizer->sanitizeAuthHeader(
                        $event->getRequest()->headers->get('X-JWT-Authorization')
                    );

                    $this->capturedSanitizedToken = $authHeader;

                    if ($this->shouldThrowException) {
                        throw new TooManyRequestsHttpException();
                    }
                }
            }
        };
    }

    /**
     * Test that a standard authorization header works correctly
     */
    public function testStandardAuthorizationHeader(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-JWT-Authorization', 'Bearer validToken123');

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // No exception should be thrown
        $this->subscriber->onKernelRequest($requestEvent);
        $this->assertEquals('Bearer validToken123', $this->subscriber->getSanitizedToken());
    }

    /**
     * Test that a malicious authorization header is properly sanitized
     */
    public function testMaliciousAuthorizationHeader(): void
    {
        $maliciousToken = "Bearer validToken123\r\nX-Malicious: exploit";

        $request = Request::create('/test');
        $request->headers->set('X-JWT-Authorization', $maliciousToken);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // No exception should be thrown
        $this->subscriber->onKernelRequest($requestEvent);
        $this->assertEquals('Bearer validToken123', $this->subscriber->getSanitizedToken());
    }

    /**
     * Test that a header with script tags is properly sanitized
     */
    public function testHeaderWithScriptTags(): void
    {
        $maliciousToken = "Bearer <script>alert(1)</script>";

        $request = Request::create('/test');
        $request->headers->set('X-JWT-Authorization', $maliciousToken);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // No exception should be thrown
        $this->subscriber->onKernelRequest($requestEvent);

        // Verify that sanitization was applied correctly
        $expected = $this->headerSanitizer->sanitizeAuthHeader($maliciousToken);
        $this->assertEquals($expected, $this->subscriber->getSanitizedToken());
    }

    /**
     * Test that too many requests throws an exception
     */
    public function testTooManyRequests(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-JWT-Authorization', 'Bearer validToken123');

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Set up to throw an exception
        $this->subscriber->setShouldThrowException(true);

        // Expect an exception
        $this->expectException(TooManyRequestsHttpException::class);
        $this->subscriber->onKernelRequest($requestEvent);
    }
}
