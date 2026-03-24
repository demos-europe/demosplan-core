<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\EventListener;

use demosplan\DemosPlanCoreBundle\EventListener\SecurityValidationListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SecurityValidationListenerTest extends TestCase
{
    private const TEST_PATH = '/test';
    private const NULL_BYTE_VALUE = "value\0injection";
    private const NULL_BYTE_MESSAGE = 'Invalid input detected: null byte';
    private const LIMITS_MESSAGE = 'Request exceeds allowed limits';
    private const MALICIOUS_PATTERN_MESSAGE = 'Malicious pattern detected';

    private SecurityValidationListener $sut;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sut = new SecurityValidationListener($this->logger);
    }

    private function createRequestEvent(Request $request, bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $requestType);
    }

    // ========== Null Byte Detection Tests ==========

    public function testNullByteInQueryParamIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['param' => self::NULL_BYTE_VALUE]);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::NULL_BYTE_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testNullByteInPostDataIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'POST', [], [], [], [], "field=value\0injection");
        $request->request->set('field', self::NULL_BYTE_VALUE);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::NULL_BYTE_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testNullByteInHeaderIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH);
        $request->headers->set('X-Custom', self::NULL_BYTE_VALUE);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::NULL_BYTE_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testNullByteInCookieIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH);
        $request->cookies->set('session', self::NULL_BYTE_VALUE);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::NULL_BYTE_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testNullByteInRawBodyIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'POST', [], [], [], [], "raw body with \0 null byte");
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::NULL_BYTE_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testNullByteInArrayKeyIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ["key\0injection" => 'value']);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::NULL_BYTE_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testNullByteInNestedArrayIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', [
            'filters' => [
                'nested' => [
                    'field' => self::NULL_BYTE_VALUE,
                ],
            ],
        ]);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::NULL_BYTE_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    // ========== DoS Protection Tests ==========

    public function testExcessivelyLongParameterNameIsRejected(): void
    {
        $longKey = str_repeat('a', 501);
        $request = Request::create(self::TEST_PATH, 'GET', [$longKey => 'value']);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::LIMITS_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testExcessivelyLongParameterValueIsRejected(): void
    {
        $longValue = str_repeat('a', 50001);
        $request = Request::create(self::TEST_PATH, 'GET', ['param' => $longValue]);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::LIMITS_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testDeeplyNestedArrayIsRejected(): void
    {
        // Create array nested 21 levels deep
        $deepArray = ['value'];
        for ($i = 0; $i < 21; ++$i) {
            $deepArray = ['nested' => $deepArray];
        }

        $request = Request::create(self::TEST_PATH, 'GET', ['data' => $deepArray]);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::LIMITS_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testArrayWithTooManyElementsIsRejected(): void
    {
        // Create array with 5001 total elements
        $largeArray = [];
        for ($i = 0; $i < 5001; ++$i) {
            $largeArray[] = 'value';
        }

        $request = Request::create(self::TEST_PATH, 'GET', ['data' => $largeArray]);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::LIMITS_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    // ========== Attack Pattern Tests ==========

    public function testPrototypePollutionIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['__proto__' => 'malicious']);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::MALICIOUS_PATTERN_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testConstructorPollutionIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['constructor' => 'malicious']);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::MALICIOUS_PATTERN_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testPrototypePollutionIsRejected2(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['prototype' => 'malicious']);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::MALICIOUS_PATTERN_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testDirectoryTraversalUnixIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['file' => '../../../etc/passwd']);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::MALICIOUS_PATTERN_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    public function testDirectoryTraversalWindowsIsRejected(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['file' => '..\\..\\..\\windows\\system32']);
        $event = $this->createRequestEvent($request);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(self::MALICIOUS_PATTERN_MESSAGE);

        $this->sut->onKernelRequest($event);
    }

    // ========== Valid Request Tests ==========

    public function testCleanRequestIsAllowed(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['param' => 'clean value']);
        $event = $this->createRequestEvent($request);

        // Should not throw
        $this->sut->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testLegitimateArrayRequestIsAllowed(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', [
            'page'    => '1',
            'search'  => 'test query',
            'filters' => ['status' => 'active', 'type' => 'user'],
        ]);
        $event = $this->createRequestEvent($request);

        // Should not throw
        $this->sut->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testSpecialCharactersAreAllowed(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', [
            'company' => 'AT&T',
            'query'   => '<script>alert(1)</script>',
            'email'   => 'user@example.com',
        ]);
        $event = $this->createRequestEvent($request);

        // Should not throw - no modification, just detection
        // XSS prevention is handled at view layer (Twig auto-escaping)
        $this->sut->onKernelRequest($event);

        // Verify original data is preserved (no escaping)
        $this->assertEquals('AT&T', $request->query->get('company'));
        $this->assertEquals('<script>alert(1)</script>', $request->query->get('query'));
        $this->addToAssertionCount(1);
    }

    public function testStaticAssetRequestsAreSkipped(): void
    {
        // Even with malicious content, static assets should be skipped
        $request = Request::create('/css/style.css', 'GET', ['param' => "malicious\0"]);
        $event = $this->createRequestEvent($request);

        // Should not throw because static assets are skipped
        $this->sut->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testTusUploadWithBinaryDataIsAllowed(): void
    {
        // TUS uploads contain binary file data with legitimate null bytes
        $binaryData = "binary\0file\0data\0with\0null\0bytes";
        $request = Request::create('/_tus/upload/54f3f6b6-e74a-443c-8fa9-3ad92ab3a5b3', 'PATCH', [], [], [], [], $binaryData);
        $event = $this->createRequestEvent($request);

        // Should not throw because TUS endpoints are skipped
        $this->sut->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testSubRequestsAreSkipped(): void
    {
        // Sub-requests should be skipped entirely
        $request = Request::create(self::TEST_PATH, 'GET', ['param' => "malicious\0"]);
        $event = $this->createRequestEvent($request, false); // false = sub-request

        // Should not throw because sub-requests are skipped
        $this->sut->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testEdgeCaseAcceptableLimits(): void
    {
        // Test values at the edge of acceptable limits
        $request = Request::create(self::TEST_PATH, 'GET', [
            str_repeat('a', 500) => str_repeat('b', 50000), // Exactly at limits
        ]);
        $event = $this->createRequestEvent($request);

        // Should not throw - exactly at limit is OK
        $this->sut->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    // ========== Logging Tests ==========

    public function testThreatIsLogged(): void
    {
        $request = Request::create(self::TEST_PATH, 'GET', ['param' => self::NULL_BYTE_VALUE]);
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $event = $this->createRequestEvent($request);

        // Expect logger to be called
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Security validation rejected request',
                $this->callback(function ($context) {
                    return 'null_byte_detected' === $context['threat_type']
                        && self::TEST_PATH === $context['path']
                        && 'GET' === $context['method']
                        && isset($context['ip']);
                })
            );

        try {
            $this->sut->onKernelRequest($event);
        } catch (BadRequestHttpException $e) {
            // Expected exception
        }
    }
}
