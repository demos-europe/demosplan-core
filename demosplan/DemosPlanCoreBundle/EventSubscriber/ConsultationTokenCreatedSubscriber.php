<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Event\ConsultationTokenCreatedEvent;
use demosplan\DemosPlanCoreBundle\Logic\TokenCreationNotifier;
use Exception;

class ConsultationTokenCreatedSubscriber extends BaseEventSubscriber
{
    public function __construct(private readonly TokenCreationNotifier $creationNotifier)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsultationTokenCreatedEvent::class => 'notifyUser',
        ];
    }

    /**
     * @throws Exception
     */
    public function notifyUser(ConsultationTokenCreatedEvent $event): void
    {
        $this->creationNotifier->notifyIfNecessary($event->getConsultationToken());
    }
}
