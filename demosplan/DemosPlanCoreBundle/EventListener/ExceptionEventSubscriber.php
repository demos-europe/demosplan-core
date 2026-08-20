<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ExceptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

use function is_array;

class ExceptionEventSubscriber implements EventSubscriberInterface
{
    /** @var LoggerInterface */
    protected $logger;

    /**
     * @var callable
     */
    private $currentController;

    public function __construct(
        LoggerInterface $logger,
        private readonly ExceptionService $exceptionService,
        private readonly bool $debug = false,
    ) {
        $this->logger = $logger;
    }

    public function trackController(ControllerEvent $controllerEvent): void
    {
        $this->currentController = $controllerEvent->getController();
    }

    /**
     * Redirect on NotFound Exception.
     *
     * @throws Throwable
     */
    public function handleException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (is_array($this->currentController) && $this->currentController[0] instanceof APIController) {
            // In debug mode, include exception details in API response for better DX
            if ($this->debug) {
                $event->setResponse($this->createDebugApiErrorResponse($exception));

                return;
            }

            $event->setResponse($this->currentController[0]->handleApiError($exception));

            return;
        }

        /*
         * API Platform operations are dispatched to its own MainController rather than to an
         * APIController, so the branch above never matches them and they would fall through to the
         * HTML error page - a redirect no API client can use. Answer them in the same error envelope
         * the other API versions use instead.
         */
        if ($this->isApiPlatformRequest($event->getRequest())) {
            $event->setResponse($this->createApiPlatformErrorResponse($exception));

            return;
        }

        if ($exception instanceof NotFoundHttpException) {
            // log 404
            $this->logger->info($exception->getMessage());
            // set custom response
            $event->setResponse($this->exceptionService->create404Response());

            return;
        }

        // improve DX by throwing exception to see error
        if ($this->debug) {
            throw $exception;
        }

        $event->setResponse($this->exceptionService->handleError($exception));
    }

    /**
     * Recognised by the request attribute API Platform sets on its own routes, rather than by the
     * controller instance: the attribute holds in both dispatch modes, whereas the controller is
     * `MainController` only while `use_symfony_listeners` is false.
     */
    private function isApiPlatformRequest(Request $request): bool
    {
        return null !== $request->attributes->get('_api_resource_class');
    }

    /**
     * Mirrors the envelope of {@see APIController::handleApiError()} so clients see one error format
     * across API versions, but derives the status from the exception itself. That mapping cannot be
     * reused: its switch predates these operations and knows none of Symfony's HTTP exceptions, so a
     * 404 would arrive as a 400.
     *
     * Messages are passed on only for the exceptions that are mapped below: those are raised by our
     * own providers and processors and worded for the client. An unmapped throwable is unexpected and
     * may carry internals, so it gets the plain status text.
     */
    private function createApiPlatformErrorResponse(Throwable $exception): APIResponse
    {
        $mappedStatus = $this->mapExceptionToStatus($exception);
        $status = $mappedStatus ?? Response::HTTP_INTERNAL_SERVER_ERROR;

        $this->logger->error('API Platform exception occurred', [
            'exception' => $exception,
            'status'    => $status,
        ]);

        $error = [
            'status' => $status,
            'title'  => null === $mappedStatus
                ? (Response::$statusTexts[$status] ?? 'Internal Server Error')
                : $exception->getMessage(),
        ];

        if ($this->debug) {
            $error['detail'] = $exception::class;
            $error['meta'] = [
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        $data = [
            'errors'   => [$error],
            'jsonapi'  => ['version' => '1.0'],
            'included' => [],
            'links'    => ['self' => ''],
        ];

        return new APIResponse($data, $status);
    }

    /**
     * Keeps the statuses of {@see APIController::handleApiError()} for the exceptions raised in this
     * codebase, so the same failure is reported alike whichever API version answers it. None of those
     * classes implements `HttpExceptionInterface`, so without this they would all surface as 500.
     *
     * One deviation, deliberate: the legacy mapping falls back to 400, reporting a server fault as the
     * client's mistake. Null marks an exception this class does not recognise, which the caller
     * answers as 500 and without passing its message on.
     */
    private function mapExceptionToStatus(Throwable $exception): ?int
    {
        return match (true) {
            $exception instanceof HttpExceptionInterface    => $exception->getStatusCode(),
            $exception instanceof ResourceNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof AccessDeniedException     => Response::HTTP_UNAUTHORIZED,
            $exception instanceof BadRequestException       => Response::HTTP_BAD_REQUEST,
            default                                         => null,
        };
    }

    /**
     * Create a detailed API error response for debugging purposes.
     * Only used in debug mode to provide comprehensive error information.
     */
    private function createDebugApiErrorResponse(Throwable $exception): APIResponse
    {
        $this->logger->error('API exception occurred', [
            'exception' => $exception,
            'backtrace' => $exception->getTraceAsString(),
        ]);

        $data = [
            'errors' => [
                [
                    'status' => Response::HTTP_BAD_REQUEST,
                    'title'  => $exception->getMessage(),
                    'detail' => $exception::class,
                    'meta'   => [
                        'file'     => $exception->getFile(),
                        'line'     => $exception->getLine(),
                        'trace'    => explode("\n", $exception->getTraceAsString()),
                        'previous' => $exception->getPrevious()?->getMessage(),
                    ],
                ],
            ],
            'jsonapi'  => ['version' => '1.0'],
            'included' => [],
            'links'    => ['self' => ''],
        ];

        return new APIResponse($data, Response::HTTP_BAD_REQUEST);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'handleException', KernelEvents::CONTROLLER => 'trackController'];
    }
}
