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

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Stringable;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @method string getHouseNumber()
 * @method string getLocalityName()
 * @method string getPostalCode()
 * @method string getStreet()
 */
class KeycloakUserData extends CommonUserData implements KeycloakUserDataInterface, Stringable
{
    protected string $houseNumber;
    protected string $localityName;
    protected string $postalCode;
    protected string $street;

    public function fill(ResourceOwnerInterface $resourceOwner): void
    {
        $userInformation = $resourceOwner->toArray();

        $this->emailAddress = $userInformation['email'] ?? '';
        $this->firstName = $userInformation['givenName'] ?? '';
        $this->houseNumber = $userInformation['houseNumber'] ?? '';
        $this->lastName = $userInformation['surname'] ?? '';
        $this->localityName = $userInformation['localityName'] ?? '';
        $this->organisationId = $userInformation['organisationId'] ?? ''; // gets orga gwId
        $this->organisationName = $userInformation['organisationName'] ?? '';
        $this->postalCode = $userInformation['postalCode'] ?? '';
        $this->street = $userInformation['street'] ?? '';
        $this->userId = $userInformation['sub'] ?? ''; // gets user gwId, always empty
        $this->userName = $userInformation['email'] ?? ''; // gets user login

        $this->lock();
        $this->checkMandatoryValuesExist();
    }

    /**
     * Checks for existing mandatory data.
     */
    public function checkMandatoryValuesExist(): void
    {
        $missingMandatoryValues = [];
        if ('' === $this->emailAddress) {
            $missingMandatoryValues[] = 'emailAddress';
        }

        if ('' === $this->firstName && '' === $this->lastName) {
            $missingMandatoryValues[] = 'name';
        }

        if ([] !== $missingMandatoryValues) {
            throw new AuthenticationCredentialsNotFoundException(implode(', ', $missingMandatoryValues).' are missing in requestValues');
        }
    }
}
