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
use demosplan\DemosPlanCoreBundle\Logic\ExceptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
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
                    'title' => $exception->getMessage(),
                    'detail' => $exception::class,
                    'meta' => [
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => explode("\n", $exception->getTraceAsString()),
                        'previous' => $exception->getPrevious()?->getMessage(),
                    ],
                ],
            ],
            'jsonapi' => ['version' => '1.0'],
            'included' => [],
            'links' => ['self' => ''],
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
