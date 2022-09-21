<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository;

class EmailAddressService extends CoreService
{
    /**
     * @var EmailAddressRepository
     */
    private $emailAddressRepository;

    public function __construct(EmailAddressRepository $emailAddressRepository)
    {
        $this->emailAddressRepository = $emailAddressRepository;
    }

    /**
     * Checks if any EmailAddress entities are not referenced anymore and if so deletes them.
     *
     * @return int the number of deletions
     */
    public function deleteOrphanEmailAddresses(): int
    {
        return $this->emailAddressRepository->deleteOrphanEmailAddresses();
    }

    public function getOrCreateEmailAddress(string $fullEmailAddress): EmailAddress
    {
        return $this->emailAddressRepository->getOrCreateEmailAddress($fullEmailAddress);
    }
}
