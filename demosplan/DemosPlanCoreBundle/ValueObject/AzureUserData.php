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
 * Simple Azure OAuth user data for already provisioned users.
 * Only handles authentication claims, not user provisioning.
 */
class AzureUserData implements AzureUserDataInterface, Stringable
{
    private string $emailAddress = '';
    private string $firstName = '';
    private string $lastName = '';
    private string $objectId = '';
    private string $subject = '';

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getObjectId(): string
    {
        return $this->objectId;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function fill(ResourceOwnerInterface $resourceOwner): void
    {
        $userInformation = $resourceOwner->toArray();

        $this->emailAddress = $userInformation['email'] ?? $userInformation['upn'] ?? $userInformation['unique_name'] ?? $userInformation['preferred_username'] ?? '';
        $this->objectId = $userInformation['oid'] ?? '';
        $this->subject = $userInformation['sub'] ?? '';
        $this->firstName = $userInformation['given_name'] ?? '';
        $this->lastName = $userInformation['family_name'] ?? '';

        // Fall back to 'name' claim if given_name/family_name are missing
        if ('' === $this->firstName && '' === $this->lastName && isset($userInformation['name'])) {
            $nameParts = explode(' ', $userInformation['name'], 2);
            $this->firstName = $nameParts[0];
            $this->lastName = $nameParts[1] ?? '';
        }

        $this->checkMandatoryValuesExist();
    }

    /**
     * Checks for mandatory authentication data.
     */
    private function checkMandatoryValuesExist(): void
    {
        if ('' === $this->emailAddress) {
            throw new AuthenticationCredentialsNotFoundException('Email address is missing in Azure OAuth response');
        }
    }

    public function __toString(): string
    {
        return sprintf(
            'emailAddress: %s, firstName: %s, lastName: %s, objectId: %s, subject: %s',
            $this->emailAddress,
            $this->firstName,
            $this->lastName,
            $this->objectId,
            $this->subject
        );
    }
}
