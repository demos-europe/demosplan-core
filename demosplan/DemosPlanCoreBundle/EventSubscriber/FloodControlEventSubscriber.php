<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Events\AddonMaintenanceEventInterface;
use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormExtraFieldsEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PublicDetailStatementListLoadedEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationFloodEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationStrictEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\Exception\HoneypotException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\FloodControlService;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FloodControlEventSubscriber implements EventSubscriberInterface
{
    protected FloodControlService $floodControl;

    public function __construct(FloodControlService $floodControl)
    {
        $this->floodControl = $floodControl;
    }

    /**
     * Subscribe quite early to kernel.request to throw an exception if request
     * is not valid.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RequestValidationStrictEvent::class                  => 'handleRequestValidation',
            RequestValidationFloodEvent::class                   => 'handleRequestValidation',
            RequestValidationWeakEvent::class                    => 'handleRequestValidation',
            PublicDetailStatementListLoadedEvent::class          => 'onPublicDetailStatementListLoaded',
            AddonMaintenanceEventInterface::class                => 'onMaintenance',
            TwigExtensionFormExtraFieldsEvent::class             => 'onFormExtraFields',
        ];
    }

    /**
     * Validate input data based on configured checks.
     *
     * @throws HoneypotException
     */
    public function handleRequestValidation(RequestValidationEvent $event): void
    {
        switch (true) {
            case $event instanceof RequestValidationWeakEvent:
                $this->floodControl->checkHoneypot($event);
                break;
            case $event instanceof RequestValidationFloodEvent:
                $this->floodControl->checkHoneypot($event);
                $this->floodControl->checkFlood($event);
                break;
            case $event instanceof RequestValidationStrictEvent:
                $this->floodControl->checkHoneypot($event);
                $this->floodControl->checkFlood($event);
                $this->floodControl->checkCookie($event);
                break;
            default:
                throw new InvalidArgumentException('Invalid Event thrown');
        }
    }

    /**
     * Provide Markup for honeypot check.
     */
    public function onFormExtraFields(TwigExtensionFormExtraFieldsEvent $event): void
    {
        $this->floodControl->getHoneypotMarkup($event);
    }

    /**
     * Perform Cleanup operations when the maintenance-service triggers
     * the dplan.maintenance event.
     *
     * @throws Exception $exception
     */
    public function onMaintenance(AddonMaintenanceEventInterface $event): void
    {
        $this->floodControl->cleanRecords();
    }

    public function onPublicDetailStatementListLoaded(PublicDetailStatementListLoadedEvent $event): void
    {
        $this->floodControl->extractStatementUserLikeIds($event);
    }
}
