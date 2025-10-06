<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Services\SubdomainHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Custom Eventlistener
 * Class SubdomainListener.
 */
class SubdomainEventSubscriber implements EventSubscriberInterface
{
    /** @var SubdomainHandlerInterface */
    protected $subdomainHandler;

    public function __construct(SubdomainHandlerInterface $subdomainHandler)
    {
        $this->subdomainHandler = $subdomainHandler;
    }

    /**
     * Set subdomain from request.
     */
    public function handle(RequestEvent $event)
    {
        $this->subdomainHandler->setSubdomainParameter($event->getRequest());
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['handle', 22]];
    }
}
