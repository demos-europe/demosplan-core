<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Event\ConsultationTokenCreatedEvent;
use demosplan\DemosPlanCoreBundle\Logic\TokenCreationNotifier;
use Exception;

class ConsultationTokenCreatedSubscriber extends BaseEventSubscriber
{
    /**
     * @var TokenCreationNotifier
     */
    private $creationNotifier;

    public function __construct(TokenCreationNotifier $creationNotifier)
    {
        $this->creationNotifier = $creationNotifier;
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
