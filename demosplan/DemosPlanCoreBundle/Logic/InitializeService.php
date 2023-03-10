<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Services\initializeServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Traits\IsProfilableTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;

/**
 * This service uses Dependency Injection to use private services
 * Class InitializeService.
 */
class InitializeService implements initializeServiceInterface
{
    use IsProfilableTrait;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var MessageBagInterface
     */
    protected $messageBag;
    /**
     * @var RequestStack
     */
    protected $requestStack;
    /**
     * @var SessionHandler
     */
    protected $sessionHandler;

    public function __construct(
        LoggerInterface $logger,
        MessageBagInterface $messageBag,
        RequestStack $requestStack,
        SessionHandler $sessionHandler
    ) {
        $this->logger = $logger;
        $this->messageBag = $messageBag;
        $this->requestStack = $requestStack;
        $this->sessionHandler = $sessionHandler;
    }

    public function initialize(array $context): void
    {
        $profilerName = 'Initialize';
        $this->profilerStart($profilerName);

        try {
            $this->sessionHandler->initialize($context);
            $this->profilerStop($profilerName);
        } catch (AccessDeniedException $e) {
            $this->profilerStop($profilerName);
            throw $e;
        } catch (Exception $e) {
            $this->profilerStop($profilerName);
            $this->logger->error('Initialize not successful', [$e]);
            throw new SessionUnavailableException('Initialization not successful: '.$e->getMessage(), 666666);
        }
    }
}
