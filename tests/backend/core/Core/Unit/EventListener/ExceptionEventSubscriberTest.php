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

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\EventListener\ExceptionEventSubscriber;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ExceptionService;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

class ExceptionEventSubscriberTest extends TestCase
{
    private const TEST_ERROR_MESSAGE = 'Test error';

    private LoggerInterface&MockObject $logger;
    private ExceptionService&MockObject $exceptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->exceptionService = $this->createMock(ExceptionService::class);
    }

    private function createSut(bool $debug): ExceptionEventSubscriber
    {
        return new ExceptionEventSubscriber(
            $this->logger,
            $this->exceptionService,
            $debug
        );
    }

    private function createExceptionEvent(Exception $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test');

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }

    private function createControllerEvent(callable $controller): ControllerEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test');

        return new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    /**
     * Create a callable array that represents an API controller action.
     * We need to add the method to the mock for PHP to consider it callable.
     *
     * @return array{0: APIController&MockObject, 1: string}
     */
    private function createApiControllerCallable(): array
    {
        $apiController = $this->getMockBuilder(APIController::class)
            ->disableOriginalConstructor()
            ->addMethods(['someAction'])
            ->getMock();

        return [$apiController, 'someAction'];
    }

    // ========== Event Subscription Tests ==========

    public function testSubscribedEvents(): void
    {
        $events = ExceptionEventSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        self::assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        self::assertSame('handleException', $events[KernelEvents::EXCEPTION]);
        self::assertSame('trackController', $events[KernelEvents::CONTROLLER]);
    }

    // ========== API Controller Debug Mode Tests ==========

    public function testDebugModeApiControllerReturnsDetailedErrorResponse(): void
    {
        $sut = $this->createSut(debug: true);

        // Set up API controller
        $controllerCallable = $this->createApiControllerCallable();
        $controllerEvent = $this->createControllerEvent($controllerCallable);
        $sut->trackController($controllerEvent);

        // Create exception and event
        $exception = new Exception('Test error message');
        $exceptionEvent = $this->createExceptionEvent($exception);

        // Execute
        $sut->handleException($exceptionEvent);

        // Assert response was set
        $response = $exceptionEvent->getResponse();
        self::assertInstanceOf(APIResponse::class, $response);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        // Assert response contains detailed error info
        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('errors', $content);
        self::assertCount(1, $content['errors']);

        $error = $content['errors'][0];
        self::assertSame(Response::HTTP_BAD_REQUEST, $error['status']);
        self::assertSame('Test error message', $error['title']);
        self::assertSame(Exception::class, $error['detail']);
        self::assertArrayHasKey('meta', $error);
        self::assertArrayHasKey('file', $error['meta']);
        self::assertArrayHasKey('line', $error['meta']);
        self::assertArrayHasKey('trace', $error['meta']);
    }

    public function testDebugModeApiControllerIncludesPreviousExceptionMessage(): void
    {
        $sut = $this->createSut(debug: true);

        // Set up API controller
        $controllerCallable = $this->createApiControllerCallable();
        $controllerEvent = $this->createControllerEvent($controllerCallable);
        $sut->trackController($controllerEvent);

        // Create exception with previous
        $previousException = new Exception('Previous error');
        $exception = new Exception('Main error', 0, $previousException);
        $exceptionEvent = $this->createExceptionEvent($exception);

        // Execute
        $sut->handleException($exceptionEvent);

        // Assert response contains previous exception info
        $content = json_decode($exceptionEvent->getResponse()->getContent(), true);
        self::assertSame('Previous error', $content['errors'][0]['meta']['previous']);
    }

    public function testDebugModeApiControllerLogsException(): void
    {
        $sut = $this->createSut(debug: true);

        // Set up API controller
        $controllerCallable = $this->createApiControllerCallable();
        $controllerEvent = $this->createControllerEvent($controllerCallable);
        $sut->trackController($controllerEvent);

        // Expect logger to be called
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'API exception occurred',
                self::callback(static function ($context) {
                    return isset($context['exception'], $context['backtrace']);
                })
            );

        $exception = new Exception(self::TEST_ERROR_MESSAGE);
        $exceptionEvent = $this->createExceptionEvent($exception);

        $sut->handleException($exceptionEvent);
    }

    // ========== API Controller Non-Debug Mode Tests ==========

    public function testNonDebugModeApiControllerCallsHandleApiError(): void
    {
        $sut = $this->createSut(debug: false);

        // Set up API controller with handleApiError expectation
        $expectedResponse = new APIResponse(['errors' => []], Response::HTTP_BAD_REQUEST);
        $apiController = $this->getMockBuilder(APIController::class)
            ->disableOriginalConstructor()
            ->addMethods(['someAction'])
            ->onlyMethods(['handleApiError'])
            ->getMock();
        $apiController
            ->expects(self::once())
            ->method('handleApiError')
            ->willReturn($expectedResponse);

        $controllerEvent = $this->createControllerEvent([$apiController, 'someAction']);
        $sut->trackController($controllerEvent);

        // Create exception
        $exception = new Exception(self::TEST_ERROR_MESSAGE);
        $exceptionEvent = $this->createExceptionEvent($exception);

        // Execute
        $sut->handleException($exceptionEvent);

        // Assert the original handleApiError response was used
        self::assertSame($expectedResponse, $exceptionEvent->getResponse());
    }

    // ========== Non-API Controller Tests ==========

    public function testDebugModeNonApiControllerThrowsException(): void
    {
        $sut = $this->createSut(debug: true);

        // Set up non-API controller (regular callable)
        $controller = function () { return new Response(); };
        $controllerEvent = $this->createControllerEvent($controller);
        $sut->trackController($controllerEvent);

        $exception = new Exception(self::TEST_ERROR_MESSAGE);
        $exceptionEvent = $this->createExceptionEvent($exception);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(self::TEST_ERROR_MESSAGE);

        $sut->handleException($exceptionEvent);
    }

    public function testNonDebugModeNonApiControllerCallsExceptionService(): void
    {
        $sut = $this->createSut(debug: false);

        // Set up non-API controller
        $controller = function () { return new Response(); };
        $controllerEvent = $this->createControllerEvent($controller);
        $sut->trackController($controllerEvent);

        $expectedResponse = new Response('Error page', Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->exceptionService
            ->expects(self::once())
            ->method('handleError')
            ->willReturn($expectedResponse);

        $exception = new Exception(self::TEST_ERROR_MESSAGE);
        $exceptionEvent = $this->createExceptionEvent($exception);

        $sut->handleException($exceptionEvent);

        self::assertSame($expectedResponse, $exceptionEvent->getResponse());
    }

    // ========== NotFoundHttpException Tests ==========

    public function testNotFoundExceptionReturns404Response(): void
    {
        $sut = $this->createSut(debug: false);

        // Set up non-API controller
        $controller = function () { return new Response(); };
        $controllerEvent = $this->createControllerEvent($controller);
        $sut->trackController($controllerEvent);

        $expectedResponse = new RedirectResponse('/404');
        $this->exceptionService
            ->expects(self::once())
            ->method('create404Response')
            ->willReturn($expectedResponse);

        $this->logger
            ->expects(self::once())
            ->method('info');

        $exception = new NotFoundHttpException('Page not found');
        $exceptionEvent = $this->createExceptionEvent($exception);

        $sut->handleException($exceptionEvent);

        self::assertSame($expectedResponse, $exceptionEvent->getResponse());
    }

    public function testNotFoundExceptionWithApiControllerReturnsDetailedResponseInDebugMode(): void
    {
        $sut = $this->createSut(debug: true);

        // Set up API controller
        $controllerCallable = $this->createApiControllerCallable();
        $controllerEvent = $this->createControllerEvent($controllerCallable);
        $sut->trackController($controllerEvent);

        $exception = new NotFoundHttpException('Resource not found');
        $exceptionEvent = $this->createExceptionEvent($exception);

        $sut->handleException($exceptionEvent);

        $response = $exceptionEvent->getResponse();
        self::assertInstanceOf(APIResponse::class, $response);

        $content = json_decode($response->getContent(), true);
        self::assertSame('Resource not found', $content['errors'][0]['title']);
    }

    // ========== Response Format Tests ==========

    public function testDebugResponseHasJsonApiStructure(): void
    {
        $sut = $this->createSut(debug: true);

        $controllerCallable = $this->createApiControllerCallable();
        $controllerEvent = $this->createControllerEvent($controllerCallable);
        $sut->trackController($controllerEvent);

        $exception = new Exception('Test');
        $exceptionEvent = $this->createExceptionEvent($exception);

        $sut->handleException($exceptionEvent);

        $content = json_decode($exceptionEvent->getResponse()->getContent(), true);

        self::assertArrayHasKey('errors', $content);
        self::assertArrayHasKey('jsonapi', $content);
        self::assertArrayHasKey('included', $content);
        self::assertArrayHasKey('links', $content);
        self::assertSame(['version' => '1.0'], $content['jsonapi']);
        self::assertSame([], $content['included']);
    }

    public function testDebugResponseTraceIsArray(): void
    {
        $sut = $this->createSut(debug: true);

        $controllerCallable = $this->createApiControllerCallable();
        $controllerEvent = $this->createControllerEvent($controllerCallable);
        $sut->trackController($controllerEvent);

        $exception = new Exception('Test');
        $exceptionEvent = $this->createExceptionEvent($exception);

        $sut->handleException($exceptionEvent);

        $content = json_decode($exceptionEvent->getResponse()->getContent(), true);

        self::assertIsArray($content['errors'][0]['meta']['trace']);
        self::assertNotEmpty($content['errors'][0]['meta']['trace']);
    }

    // ========== Controller Tracking Tests ==========

    public function testTrackControllerStoresController(): void
    {
        $sut = $this->createSut(debug: true);

        // First track a non-API controller
        $controller = function () { return new Response(); };
        $controllerEvent = $this->createControllerEvent($controller);
        $sut->trackController($controllerEvent);

        // Exception should be thrown (debug + non-API)
        $exception = new Exception('Test');
        $exceptionEvent = $this->createExceptionEvent($exception);

        $this->expectException(Exception::class);
        $sut->handleException($exceptionEvent);
    }

    public function testApiControllerArrayFormatIsRecognized(): void
    {
        $sut = $this->createSut(debug: true);

        // Track API controller in array format [$controller, 'method']
        $controllerCallable = $this->createApiControllerCallable();
        $controllerEvent = $this->createControllerEvent($controllerCallable);
        $sut->trackController($controllerEvent);

        $exception = new Exception('Test');
        $exceptionEvent = $this->createExceptionEvent($exception);

        // Should NOT throw - should return API response
        $sut->handleException($exceptionEvent);

        self::assertInstanceOf(APIResponse::class, $exceptionEvent->getResponse());
    }

    // ========== API Platform Tests ==========

    /**
     * API Platform dispatches to its own controller, so the APIController branch never matches. Its
     * requests are recognised by the resource class attribute instead.
     */
    private function createApiPlatformExceptionEvent(Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/3.0/Bookmark');
        $request->attributes->set('_api_resource_class', 'demosplan\DemosPlanCoreBundle\Api\Bookmark\BookmarkResource');

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }

    /**
     * @return array<string, array{0: Throwable, 1: int, 2: bool}> exception, expected status, whether
     *                                                             its message reaches the client
     */
    public static function apiPlatformExceptionProvider(): array
    {
        return [
            // Symfony HTTP exceptions carry their own status.
            'not found'            => [new NotFoundHttpException(self::TEST_ERROR_MESSAGE), Response::HTTP_NOT_FOUND, true],
            'bad request'          => [new BadRequestHttpException(self::TEST_ERROR_MESSAGE), Response::HTTP_BAD_REQUEST, true],
            'unprocessable'        => [new UnprocessableEntityHttpException(self::TEST_ERROR_MESSAGE), Response::HTTP_UNPROCESSABLE_ENTITY, true],
            // The codebase's own exceptions keep the statuses the legacy API stack gives them.
            'domain not found'     => [new ResourceNotFoundException(self::TEST_ERROR_MESSAGE), Response::HTTP_NOT_FOUND, true],
            'domain access denied' => [new AccessDeniedException(self::TEST_ERROR_MESSAGE), Response::HTTP_UNAUTHORIZED, true],
            'domain bad request'   => [new BadRequestException(self::TEST_ERROR_MESSAGE), Response::HTTP_BAD_REQUEST, true],
            /*
             * Anything unmapped is a server fault, not the client's: a Webmozart assertion guards an
             * invariant, so its failure means this code is wrong. Mapping it to 400 would disguise a
             * bug as a bad request. Its message is withheld because it may carry internals.
             */
            'assertion failure'    => [new InvalidArgumentException(self::TEST_ERROR_MESSAGE), Response::HTTP_INTERNAL_SERVER_ERROR, false],
            'unexpected failure'   => [new Exception(self::TEST_ERROR_MESSAGE), Response::HTTP_INTERNAL_SERVER_ERROR, false],
        ];
    }

    /**
     * @dataProvider apiPlatformExceptionProvider
     */
    public function testApiPlatformExceptionIsRenderedWithMappedStatus(Throwable $exception, int $expectedStatus, bool $messageIsExposed): void
    {
        $sut = $this->createSut(debug: false);
        $exceptionEvent = $this->createApiPlatformExceptionEvent($exception);

        $sut->handleException($exceptionEvent);

        $response = $exceptionEvent->getResponse();
        self::assertInstanceOf(APIResponse::class, $response);
        self::assertSame($expectedStatus, $response->getStatusCode());

        $error = json_decode((string) $response->getContent(), true)['errors'][0];
        self::assertSame($expectedStatus, $error['status']);

        if ($messageIsExposed) {
            self::assertSame(self::TEST_ERROR_MESSAGE, $error['title']);

            return;
        }

        self::assertNotSame(self::TEST_ERROR_MESSAGE, $error['title']);
    }

    /**
     * Without this branch the exception would reach the generic handler and the client would receive a
     * redirect to the HTML error page instead of a parsable body.
     */
    public function testApiPlatformRequestDoesNotFallThroughToTheHtmlErrorPage(): void
    {
        $sut = $this->createSut(debug: false);
        $this->exceptionService->expects(self::never())->method('handleError');
        $this->exceptionService->expects(self::never())->method('create404Response');

        $exceptionEvent = $this->createApiPlatformExceptionEvent(new NotFoundHttpException('gone'));
        $sut->handleException($exceptionEvent);

        $response = $exceptionEvent->getResponse();
        self::assertInstanceOf(APIResponse::class, $response);
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * A plain request without the attribute must keep its previous behaviour.
     */
    public function testRequestWithoutApiPlatformAttributeStillUsesTheGenericHandler(): void
    {
        $sut = $this->createSut(debug: false);
        $this->exceptionService->expects(self::once())
            ->method('handleError')
            ->willReturn(new Response('generic'));

        $exceptionEvent = $this->createExceptionEvent(new Exception('boom'));
        $sut->handleException($exceptionEvent);

        self::assertSame('generic', $exceptionEvent->getResponse()?->getContent());
    }
}
