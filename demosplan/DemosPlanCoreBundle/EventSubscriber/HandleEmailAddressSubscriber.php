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
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

class HandleEmailAddressSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EmailAddressRepository $emailAddressRepository
    ) {
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
    public function handleEmailAddresses(HandleEmailAddressesEventInterface $event)
    {
        $inputEmailAddressStrings = $event->getInputEmailAddressStrings();

        $savedAllowedMailAddresses = [];
        foreach ($inputEmailAddressStrings as $addressString) {
            try {
                $savedAllowedMailAddresses[] = $this->emailAddressRepository->getOrCreateEmailAddress($addressString);
            } catch (Throwable $e) {
                $this->logger->error('Could not get or create EmailAdress', [
                    'EmailAddress'      => $addressString,
                    'exception'         => $e->getMessage(),
                ]);
            }
        }

        $this->emailAddressRepository->persistEntities($savedAllowedMailAddresses);
    }
}
