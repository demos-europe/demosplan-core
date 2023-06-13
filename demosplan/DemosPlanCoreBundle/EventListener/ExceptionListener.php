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
use demosplan\DemosPlanCoreBundle\Logic\ExceptionService;

use function is_array;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ExceptionListener
{
    /** @var LoggerInterface */
    protected $logger;

    /**
     * @var ExceptionService
     */
    private $exceptionService;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var callable
     */
    private $currentController;

    public function __construct(
        LoggerInterface $logger,
        ExceptionService $exceptionService,
        bool $debug = false
    ) {
        $this->logger = $logger;
        $this->exceptionService = $exceptionService;
        $this->debug = $debug;
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
            $event->setResponse($this->currentController[0]->handleApiError($exception));

            return;
        }

        // improve DX by throwing exception to see error
        if ($this->debug) {
            throw $exception;
        }

        if ($exception instanceof NotFoundHttpException) {
            // log 404
            $this->logger->info($exception->getMessage());
            // set custom response
            $event->setResponse($this->exceptionService->create404Response());

            return;
        }

        $event->setResponse($this->exceptionService->handleError($exception));
    }
}
