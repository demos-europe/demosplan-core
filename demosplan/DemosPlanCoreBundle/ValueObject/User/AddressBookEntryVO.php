<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\User;

use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AddressBookEntryVO.
 *
 * @method getId()
 * @method getName()
 * @method setName(string $name)
 * @method getEmailAddress()
 * @method setEmailAddress(string $emailAddress)
 * @method getOrganisation()
 * @method setOrganisation(Orga $organisation)
 */
class AddressBookEntryVO extends ValueObject
{
    /** @var string */
    protected $id;

    /** @var string */
    protected $name;

    /** @var Orga */
    protected $organisation;

    /**
     * @Assert\NotBlank(message = "email.address.invalid")
     * @Assert\Email(message = "email.address.invalid")
     *
     * @var string
     */
    protected $emailAddress;

    public function __construct(string $name, string $emailAddress, Orga $organisation)
    {
        $this->setName($name);
        $this->setEmailAddress($emailAddress);
        $this->setOrganisation($organisation);
        $this->lock();
    }

    public function generateEntity()
    {
        return new AddressBookEntry($this->getName(), $this->getEmailAddress(), $this->getOrganisation());
    }
}
