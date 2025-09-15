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
    private string $objectId = '';
    private string $subject = '';

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
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

        $this->emailAddress = $userInformation['email'] ?? $userInformation['upn'] ?? $userInformation['unique_name'] ?? '';
        $this->objectId = $userInformation['oid'] ?? '';
        $this->subject = $userInformation['sub'] ?? '';

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
            'emailAddress: %s, objectId: %s, subject: %s',
            $this->emailAddress,
            $this->objectId,
            $this->subject
        );
    }
}
