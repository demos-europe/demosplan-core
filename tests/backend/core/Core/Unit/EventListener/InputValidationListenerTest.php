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

use demosplan\DemosPlanCoreBundle\EventListener\InputValidationListener;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\ValidationLoggerInterface;
use demosplan\DemosPlanCoreBundle\Service\InputValidationService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Tests\Base\UnitTestCase;

class InputValidationListenerTest extends UnitTestCase
{
    /**
     * @var InputValidationListener
     */
    protected $sut;
    private ?InputValidationService $validationService;
    private ?ValidationLoggerInterface $validationLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validationService = $this->createMock(InputValidationService::class);
        $this->validationLogger = $this->createMock(ValidationLoggerInterface::class);

        $this->sut = new InputValidationListener(
            $this->validationService,
            $this->validationLogger
        );
    }

    public function testOnKernelRequestValid(): void
    {
        $request = new Request();

        // Create a mock RequestEvent
        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);

        // Validation should be successful
        $this->validationService->expects($this->once())
            ->method('validateRequest')
            ->with($request);

        // Event should not set any response
        $event->expects($this->never())
            ->method('setResponse');

        $this->sut->onKernelRequest($event);
    }

    public function testOnKernelRequestInvalid(): void
    {
        $request = new Request();

        // Create a mock RequestEvent
        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);

        // Create a validation exception
        $exception = $this->createMock(InvalidDataException::class);
        $exception->method('getRequest')->willReturn($request);
        $exception->method('getStatusCode')->willReturn(Response::HTTP_BAD_REQUEST);

        // Validation should fail
        $this->validationService->expects($this->once())
            ->method('validateRequest')
            ->with($request)
            ->willThrowException($exception);

        // Logger should log the failure
        $this->validationLogger->expects($this->once())
            ->method('logValidationFailure')
            ->with($request, $exception);

        // Event should set an error response
        $event->expects($this->once())
            ->method('setResponse')
            ->with($this->callback(function ($response) {
                return $response instanceof Response &&
                       $response->getStatusCode() === Response::HTTP_BAD_REQUEST;
            }));

        $this->sut->onKernelRequest($event);
    }

    public function testOnKernelRequestSkipNonMainRequest(): void
    {
        // Create a mock RequestEvent that's not a main request
        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(false);

        // Validation should not be called
        $this->validationService->expects($this->never())
            ->method('validateRequest');

        $this->sut->onKernelRequest($event);
    }

    public function testOnKernelRequestSkipAssetRequest(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/css/style.css']);

        // Create a mock RequestEvent
        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);

        // Validation should not be called for asset requests
        $this->validationService->expects($this->never())
            ->method('validateRequest');

        $this->sut->onKernelRequest($event);
    }

    public function testApiErrorResponse(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/test']);

        // Create a mock RequestEvent
        $event = $this->createMock(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $event->method('getRequest')->willReturn($request);

        // Create a validation exception
        $exception = $this->createMock(InvalidDataException::class);
        $exception->method('getRequest')->willReturn($request);
        $exception->method('getStatusCode')->willReturn(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Validation should fail
        $this->validationService->expects($this->once())
            ->method('validateRequest')
            ->with($request)
            ->willThrowException($exception);

        // Event should set a JSON error response for API requests
        $event->expects($this->once())
            ->method('setResponse')
            ->with($this->callback(function ($response) {
                return $response instanceof JsonResponse &&
                       $response->getStatusCode() === Response::HTTP_UNPROCESSABLE_ENTITY &&
                       $response->getContent() === json_encode(['error' => [
                           'message' => 'Validation failed',
                           'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                       ]]);
            }));

        $this->sut->onKernelRequest($event);
    }
}
