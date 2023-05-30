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
    /**
     * @var string
     */
    private $legalName;

    /**
     * @var string
     */
    private $street;

    /**
     * @var string
     */
    private $postalCode;

    /**
     * @var string
     */
    private $city;

    /**
     * @var string
     */
    private $email;

    public function __construct(
        string $legalName,
        string $street = '',
        string $postalCode = '',
        string $city = '',
        string $email = ''
    ) {
        $this->legalName = $legalName;
        $this->street = $street;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->email = $email;
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
