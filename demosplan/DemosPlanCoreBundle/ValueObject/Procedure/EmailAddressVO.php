<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Procedure;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Symfony\Component\Validator\Constraints as Assert;

class EmailAddressVO extends ValueObject
{
    /**
     * @param string $fullAddress
     */
    public function __construct(#[Assert\NotBlank(message: 'email.address.invalid')]
    #[Assert\Email(message: 'email.address.invalid')]
    protected $fullAddress = null)
    {
    }

    /**
     * @return string
     */
    public function getFullAddress()
    {
        return $this->fullAddress;
    }

    /**
     * @param string $fullAddress
     *
     * @return $this
     */
    public function setFullAddress($fullAddress)
    {
        $this->fullAddress = $fullAddress;

        return $this;
    }
}
