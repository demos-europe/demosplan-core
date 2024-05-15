<?php

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


class KeycloakUserData extends CommonUserData implements KeycloakUserDataInterface, Stringable
{
    public function fill(ResourceOwnerInterface $resourceOwner): void
    {
        $userInformation = $resourceOwner->toArray();

        $this->emailAddress = $userInformation['email'] ?? '';
        $this->firstName = $userInformation['given_name'] ?? '';
        $this->lastName = $userInformation['family_name'] ?? '';
        $this->organisationId = $userInformation['organisationId'] ?? '';
        $this->organisationName = $userInformation['organisationName'] ?? '';
        $this->userId = $userInformation['sub'] ?? '';
        $this->userName = $userInformation['preferred_username'] ?? ''; // kind of "login" //has to be unique?

        $this->lock();
        $this->checkMandatoryValuesExist();
    }
}
