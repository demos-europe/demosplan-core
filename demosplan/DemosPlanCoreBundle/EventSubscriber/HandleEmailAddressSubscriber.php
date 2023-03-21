<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Events\HandleEmailAddressesEventInterface;
use demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository;
use Doctrine\ORM\ORMException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use Psr\Log\LoggerInterface;

class HandleEmailAddressSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            HandleEmailAddressesEventInterface::class => 'handleEmailAddresses',
            ];
    }

    /**
     * @throws ORMException
     */
    public function handleEmailAddresses(HandleEmailAddressesEventInterface $event, EmailAddressRepository $emailAddressRepository)
    {
        $inputEmailAddressStrings = $event->getInputEmailAddressStrings();

        foreach ($inputEmailAddressStrings as $addressString) {
            try {
                $savedAllowedMailAddresses[] = $emailAddressRepository->getOrCreateEmailAddress($addressString);
            } catch (Throwable $e) {
                $this->logger->error('Could not get or create EmailAdress', [
                    'EmailAddress'      => $addressString,
                    'exception'         => $e->getMessage(),
                ]);
            }
        }

        $emailAddressRepository->persistEntities($savedAllowedMailAddresses);
    }
}
