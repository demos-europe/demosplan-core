<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

class OrgaSignatureValueObject
{
    public function __construct(private readonly string $legalName, private readonly string $street = '', private readonly string $postalCode = '', private readonly string $city = '', private readonly string $email = '')
    {
    }

    public function getLegalName(): string
    {
        return $this->legalName;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
