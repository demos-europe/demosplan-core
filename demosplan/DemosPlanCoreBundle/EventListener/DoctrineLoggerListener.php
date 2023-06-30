<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

class DoctrineLoggerListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if (($entity instanceof User) &&
            ('' === $entity->getPassword() || null === $entity->getPassword())) {
            $this->logger->info('User has no password ', ['backtrace' => debug_backtrace()]);
        }
    }
}
