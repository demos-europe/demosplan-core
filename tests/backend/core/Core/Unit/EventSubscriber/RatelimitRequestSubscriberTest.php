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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RatelimitRequestSubscriberTest extends TestCase
{
    private const TEST_URL = '/test';
    private const VALID_TOKEN = 'Bearer validToken123';
    private const MALICIOUS_TOKEN = "Bearer validToken123\r\nX-Malicious: exploit";
    private const SCRIPT_TOKEN = 'Bearer <script>alert(1)</script>';

    private HeaderSanitizerService $headerSanitizer;
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->headerSanitizer = new HeaderSanitizerService();
        $this->storage = new InMemoryStorage();
    }

    private function createSubscriber(
        int $limit = 10,
        bool|string $rateLimitEnabled = true,
        ?LoggerInterface $logger = null,
    ): RatelimitRequestSubscriber {
        $factory = new RateLimiterFactory(
            ['id' => 'jwt_token', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour'],
            $this->storage,
        );
        $parameterBag = new ParameterBag(['ratelimit_api_enable' => $rateLimitEnabled]);

        return new RatelimitRequestSubscriber(
            $this->headerSanitizer,
            $logger ?? $this->createMock(LoggerInterface::class),
            $parameterBag,
            $factory,
        );
    }

    private function createRequestEvent(string $token): RequestEvent
    {
        $request = Request::create(self::TEST_URL);
        $request->headers->set('X-JWT-Authorization', $token);

        $event = $this->createMock(RequestEvent::class);
        $event->method('getRequest')->willReturn($request);

        return $event;
    }

    public function testRequestWithoutJwtHeaderIsIgnored(): void
    {
        $subscriber = $this->createSubscriber(limit: 1);

        $request = Request::create(self::TEST_URL);
        $event = $this->createMock(RequestEvent::class);
        $event->method('getRequest')->willReturn($request);

        // Should not throw even with limit of 1 — no JWT header means no rate limiting
        $subscriber->onKernelRequest($event);
        $subscriber->onKernelRequest($event);
        $this->addToAssertionCount(1);
    }

    public function testRequestWithinRateLimitIsAccepted(): void
    {
        $subscriber = $this->createSubscriber(limit: 5);
        $event = $this->createRequestEvent(self::VALID_TOKEN);

        for ($i = 0; $i < 5; $i++) {
            $subscriber->onKernelRequest($event);
        }

        $this->addToAssertionCount(1);
    }

    public function testRateLimitExceededThrowsWhenEnabled(): void
    {
        $subscriber = $this->createSubscriber(limit: 1, rateLimitEnabled: true);
        $event = $this->createRequestEvent(self::VALID_TOKEN);

        $subscriber->onKernelRequest($event);

        $this->expectException(TooManyRequestsHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testRateLimitExceededLogsWarningWhenDisabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Rate limiting for api is disabled but would have been active now.');

        $subscriber = $this->createSubscriber(limit: 1, rateLimitEnabled: false, logger: $logger);
        $event = $this->createRequestEvent(self::VALID_TOKEN);

        $subscriber->onKernelRequest($event);

        // Second request exceeds limit but should NOT throw — only log a warning
        $subscriber->onKernelRequest($event);
    }

    public function testRateLimitDisabledWithStringFalse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Rate limiting for api is disabled but would have been active now.');

        // Env vars are typically strings — 'false' must also disable rate limiting
        $subscriber = $this->createSubscriber(limit: 1, rateLimitEnabled: 'false', logger: $logger);
        $event = $this->createRequestEvent(self::VALID_TOKEN);

        $subscriber->onKernelRequest($event);

        // Should NOT throw 429 when parameter is the string 'false'
        $subscriber->onKernelRequest($event);
    }

    public function testMaliciousHeaderInjectionSharesBucketWithCleanToken(): void
    {
        $subscriber = $this->createSubscriber(limit: 1, rateLimitEnabled: true);

        // "Bearer validToken123\r\nX-Malicious: exploit" sanitizes to "Bearer validToken123"
        // so both tokens share the same rate limiter bucket
        $maliciousEvent = $this->createRequestEvent(self::MALICIOUS_TOKEN);
        $validEvent = $this->createRequestEvent(self::VALID_TOKEN);

        $subscriber->onKernelRequest($maliciousEvent);

        $this->expectException(TooManyRequestsHttpException::class);
        $subscriber->onKernelRequest($validEvent);
    }

    public function testScriptTagsInHeaderAreSanitized(): void
    {
        $subscriber = $this->createSubscriber(limit: 1, rateLimitEnabled: true);

        $scriptEvent = $this->createRequestEvent(self::SCRIPT_TOKEN);
        $validEvent = $this->createRequestEvent(self::VALID_TOKEN);

        // "Bearer <script>alert(1)</script>" sanitizes to "Bearer alert1"
        // which differs from "Bearer validToken123" — separate buckets
        $subscriber->onKernelRequest($scriptEvent);
        $subscriber->onKernelRequest($validEvent);

        $this->addToAssertionCount(1);
    }

    public function testDifferentTokensUseSeparateRateLimitBuckets(): void
    {
        $subscriber = $this->createSubscriber(limit: 1, rateLimitEnabled: true);

        $event1 = $this->createRequestEvent('Bearer tokenAAA');
        $event2 = $this->createRequestEvent('Bearer tokenBBB');

        $subscriber->onKernelRequest($event1);
        $subscriber->onKernelRequest($event2);

        $this->addToAssertionCount(1);
    }
}
