<?php

namespace Tests\Core\Core\Unit\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\EventSubscriber\CsrfSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Tests for the CsrfSubscriber with focus on token sanitization
 */
class CsrfSubscriberTest extends TestCase
{
    private CsrfSubscriber $subscriber;
    private MockObject $csrfTokenManager;
    private MockObject $messageBag;
    private MockObject $logger;
    private HeaderSanitizerService $headerSanitizer;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->headerSanitizer = new HeaderSanitizerService();

        $this->subscriber = new CsrfSubscriber(
            $this->csrfTokenManager,
            $this->messageBag,
            $this->logger,
            $this->headerSanitizer
        );
    }

    /**
     * Test that GET requests are ignored
     */
    public function testGetRequestsAreIgnored(): void
    {
        $request = Request::create('/test');

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Expect no interactions with token manager
        $this->csrfTokenManager->expects($this->never())->method('getToken');

        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Test that standard CSRF token is handled correctly
     */
    public function testStandardCsrfToken(): void
    {
        $tokenId = 'valid-token-1234';

        $request = Request::create('/test', 'POST');
        $request->headers->set('x-csrf-token', $tokenId);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Mock a valid CSRF token
        $token = new CsrfToken($tokenId, 'token-value');
        $this->csrfTokenManager->method('getToken')->with($tokenId)->willReturn($token);
        $this->csrfTokenManager->method('isTokenValid')->with($token)->willReturn(true);

        // No messages should be added to message bag
        $this->messageBag->expects($this->never())->method('add');

        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Test that malicious CSRF token is properly sanitized
     */
    public function testMaliciousCsrfToken(): void
    {
        $maliciousToken = "valid-token-1234\r\nX-Malicious: exploit";
        $sanitizedToken = $this->headerSanitizer->sanitizeCsrfToken($maliciousToken);

        // Make sure sanitization actually removed the malicious part
        $this->assertEquals('valid-token-1234', $sanitizedToken);

        $request = Request::create('/test', 'POST');
        $request->headers->set('x-csrf-token', $maliciousToken);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Mock a valid CSRF token
        $token = new CsrfToken($sanitizedToken, 'token-value');
        $this->csrfTokenManager->method('getToken')->with($sanitizedToken)->willReturn($token);
        $this->csrfTokenManager->method('isTokenValid')->with($token)->willReturn(true);

        // No messages should be added to message bag
        $this->messageBag->expects($this->never())->method('add');

        $this->subscriber->onKernelRequest($requestEvent);
    }

    /**
     * Test that a token with script tags is properly sanitized
     */
    public function testTokenWithScriptTags(): void
    {
        $maliciousToken = "valid-token-<script>alert(1)</script>";
        $sanitizedToken = $this->headerSanitizer->sanitizeCsrfToken($maliciousToken);

        $request = Request::create('/test', 'POST');
        $request->headers->set('x-csrf-token', $maliciousToken);

        $requestEvent = $this->createMock(RequestEvent::class);
        $requestEvent->method('getRequest')->willReturn($request);

        // Mock a valid CSRF token
        $token = new CsrfToken($sanitizedToken, 'token-value');
        $this->csrfTokenManager->method('getToken')->with($sanitizedToken)->willReturn($token);
        $this->csrfTokenManager->method('isTokenValid')->with($token)->willReturn(true);

        // No messages should be added to message bag
        $this->messageBag->expects($this->never())->method('add');

        $this->subscriber->onKernelRequest($requestEvent);
    }
}
