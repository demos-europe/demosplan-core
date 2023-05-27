<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Event\User\NewOrgaRegisteredEvent;
use demosplan\DemosPlanCoreBundle\Logic\Notifier\OrgaChangesNotifier;
use Psr\Log\LoggerInterface;

class NewOrgaRegisteredSubscriber extends BaseEventSubscriber
{
    /**
     * @var OrgaChangesNotifier
     */
    private $orgaChangesNotifier;

    public function __construct(
        OrgaChangesNotifier $orgaChangesNotifier,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->orgaChangesNotifier = $orgaChangesNotifier;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NewOrgaRegisteredEvent::class => 'newOrgaRegistered',
        ];
    }

    public function newOrgaRegistered(NewOrgaRegisteredEvent $event): void
    {
        $this->orgaChangesNotifier->notifyNewOrgaAdminOfRegistration(
            $event->getUserEmail(),
            $event->getOrgaTypeNames(),
            $event->getCustomerName(),
            $event->getUserFirstName(),
            $event->getUserLastName(),
            $event->getOrgaName()
        );

        $this->orgaChangesNotifier->notifyDeciderOfOrgaRegistration($event->getOrgaName());
    }
}
