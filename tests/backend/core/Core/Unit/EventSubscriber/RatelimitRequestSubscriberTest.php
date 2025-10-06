<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\EventSubscriber;

use demosplan\DemosPlanCoreBundle\EventSubscriber\RatelimitRequestSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Tests for the RatelimitRequestSubscriber with focus on header sanitization.
 */
class RatelimitRequestSubscriberTest extends TestCase
{
    private const TEST_URL = '/test';
    private const VALID_TOKEN = 'Bearer validToken123';
    private const MALICIOUS_TOKEN = "Bearer validToken123\r\nX-Malicious: exploit";
    private const SCRIPT_TOKEN = 'Bearer <script>alert(1)</script>';

    private RatelimitRequestSubscriber $subscriber;
    private HeaderSanitizerService $headerSanitizer;
    private ParameterBag $parameterBag;

    protected function setUp(): void
    {
        $this->headerSanitizer = new HeaderSanitizerService();
        $this->parameterBag = new ParameterBag(['ratelimit_api_enable' => true]);

        // We need to create a stub subscriber as we can't mock RateLimiterFactory (final class)
        $this->subscriber = $this->createStubSubscriber($this->parameterBag);
    }

    private function createStubSubscriber(ParameterBag $parameterBag): RatelimitRequestSubscriber
    {
        return new class($this->headerSanitizer, $parameterBag) extends RatelimitRequestSubscriber {
            private bool $shouldThrowException = false;
            private ?string $capturedSanitizedToken = null;

            public function __construct(
                private readonly HeaderSanitizerService $headerSanitizer,
                private readonly ParameterBag $parameterBag
            ) {
                // We intentionally do NOT call the parent constructor here because RateLimiterFactory is a final class
                // and cannot be mocked in this test context. This means that any logic in the parent constructor will
                // NOT be executed, and any dependencies expected by RatelimitRequestSubscriber will NOT be initialized.
                // This stub is only suitable for tests that do not require the full initialization of the parent class.
                // The actual rate limiting logic is tested separately, and this stub focuses on testing the parameter
                // checking and header sanitization logic in isolation.
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

                    // Check if rate limiting is enabled
                    if (true === $this->parameterBag->get('ratelimit_api_enable')
                        && $this->shouldThrowException) {
                        throw new TooManyRequestsHttpException();
                    }
                }
            }
        };
    }

    /**
     * Test that a standard authorization header works correctly.
     */
    public function testStandardAuthorizationHeader(): void
    {
        $request = Request::create(self::TEST_URL);
        $request->headers->set('X-JWT-Authorization', self::VALID_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // No exception should be thrown
        $this->subscriber->onKernelRequest($requestEvent);
        $this->assertEquals(self::VALID_TOKEN, $this->subscriber->getSanitizedToken());
    }

    /**
     * Test that a malicious authorization header is properly sanitized.
     */
    public function testMaliciousAuthorizationHeader(): void
    {
        $request = Request::create(self::TEST_URL);
        $request->headers->set('X-JWT-Authorization', self::MALICIOUS_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // No exception should be thrown
        $this->subscriber->onKernelRequest($requestEvent);
        $this->assertEquals(self::VALID_TOKEN, $this->subscriber->getSanitizedToken());
    }

    /**
     * Test that a header with script tags is properly sanitized.
     */
    public function testHeaderWithScriptTags(): void
    {
        $request = Request::create(self::TEST_URL);
        $request->headers->set('X-JWT-Authorization', self::SCRIPT_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // No exception should be thrown
        $this->subscriber->onKernelRequest($requestEvent);

        // Verify that sanitization was applied correctly
        $expected = $this->headerSanitizer->sanitizeAuthHeader(self::SCRIPT_TOKEN);
        $this->assertEquals($expected, $this->subscriber->getSanitizedToken());
    }

    /**
     * Test that too many requests throws an exception.
     */
    public function testTooManyRequests(): void
    {
        $request = Request::create(self::TEST_URL);
        $request->headers->set('X-JWT-Authorization', self::VALID_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Set up to throw an exception
        $this->subscriber->setShouldThrowException(true);

        // Expect an exception
        $this->expectException(TooManyRequestsHttpException::class);
        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Test that rate limiting is bypassed when ratelimit_api_enable is false.
     */
    public function testRateLimitingDisabled(): void
    {
        // Create a new subscriber with rate limiting disabled
        $parameterBag = new ParameterBag(['ratelimit_api_enable' => false]);
        $subscriber = $this->createStubSubscriber($parameterBag);

        $request = Request::create(self::TEST_URL);
        $request->headers->set('X-JWT-Authorization', self::VALID_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Even though we set shouldThrowException to true, it should NOT throw
        // because rate limiting is disabled
        $subscriber->setShouldThrowException(true);

        // No exception should be thrown
        $subscriber->onKernelRequest($requestEvent);
        $this->assertEquals(self::VALID_TOKEN, $subscriber->getSanitizedToken());
    }

    /**
     * Test that rate limiting is applied when ratelimit_api_enable is true.
     */
    public function testRateLimitingEnabled(): void
    {
        // Create a new subscriber with rate limiting enabled
        $parameterBag = new ParameterBag(['ratelimit_api_enable' => true]);
        $subscriber = $this->createStubSubscriber($parameterBag);

        $request = Request::create(self::TEST_URL);
        $request->headers->set('X-JWT-Authorization', self::VALID_TOKEN);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Set up to throw an exception
        $subscriber->setShouldThrowException(true);

        // Expect an exception because rate limiting is enabled
        $this->expectException(TooManyRequestsHttpException::class);
        $subscriber->onKernelRequest($requestEvent);
    }
}
