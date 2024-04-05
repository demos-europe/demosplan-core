<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Events\GetEmailIdsEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Event\GetEmailIdsEvent;
use demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EmailAddressService extends CoreService
{
    public function __construct(
        private readonly EmailAddressRepository $emailAddressRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Checks if any EmailAddress entities are not referenced anymore and if so deletes them.
     *
     * @return int the number of deletions
     */
    public function deleteOrphanEmailAddresses(): int
    {
        $event = new GetEmailIdsEvent();
        $this->eventDispatcher->dispatch($event, GetEmailIdsEventInterface::class);

        $emailIds = $event->getEmailIds();

        return $this->emailAddressRepository->deleteOrphanEmailAddresses($emailIds);
    }

    public function getOrCreateEmailAddress(string $fullEmailAddress): EmailAddress
    {
        return $this->emailAddressRepository->getOrCreateEmailAddress($fullEmailAddress);
    }
}
