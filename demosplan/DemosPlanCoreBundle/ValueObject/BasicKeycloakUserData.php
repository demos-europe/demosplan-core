<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @method array  getCustomerRoleRelations()
 * @method string getEmailAddress()
 * @method string getUserName()
 * @method string getUserId()
 * @method string getOrganisationName()
 * @method string getOrganisationId()
 * @method string getFullName()
 */
class BasicKeycloakUserData extends ValueObject implements KeycloakUserDataInterface
{
    /**
     * @var array<int, array<int,string>>
     */
    protected array $customerRoleRelations = [];

    /**
     * E-mail-address of the provided user.
     */
    protected string $emailAddress = '';

    /**
     * Unique abbreviation of chosen login name of the provided user.
     */
    protected string $userName = '';

    /**
     * Unique ID of the provided user.
     */
    protected string $userId = '';

    /**
     * Name of the provided organisation.
     */
    protected string $organisationName = '';

    /**
     * Unique identifier of the provided organisation.
     */
    protected string $organisationId = '';

    /**
     * Full (first- and last-) name of the provided user.
     */
    protected string $fullName = '';

    public function fill(ResourceOwnerInterface $resourceOwner): void
    {
        $userInformation = $resourceOwner->toArray();

        if (array_key_exists('groups', $userInformation)
            && is_array($userInformation['groups'])
        ) {
            $this->mapCustomerRoles($userInformation['groups']);
        }

        $this->userId = $userInformation['sub'] ?? '';
        $this->organisationName = $userInformation['organisationName'] ?? '';
        $this->organisationId = $userInformation['organisationId'] ?? '';
        $this->fullName = $userInformation['name'] ?? '';
        $this->userName = $userInformation['preferred_username'] ?? ''; //kind of "login" //has to be unique?
        $this->emailAddress = $userInformation['email'] ?? '';

        $this->lock();
        $this->checkMandatoryValuesExist();
    }

    /**
     * Checks for existing mandatory data.
     */
    public function checkMandatoryValuesExist(): void
    {
        $missingMandatoryValues = [];
        if ('' === $this->userId) {
            $missingMandatoryValues[] = 'userId';
        }

        if ('' === $this->userName) {
            $missingMandatoryValues[] = 'userName';
        }

        if ('' === $this->emailAddress) {
            $missingMandatoryValues[] = 'emailAddress';
        }

        if ('' === $this->organisationId) {
            $missingMandatoryValues[] = 'organisationId';
        }

        if ('' === $this->fullName) {
            $missingMandatoryValues[] = 'fullName';
        }

        if ([] === $this->customerRoleRelations) {
            $missingMandatoryValues[] = 'roles';
        }

        if ([] !== $missingMandatoryValues) {
            throw new AuthenticationCredentialsNotFoundException(
                implode(', ', $missingMandatoryValues).'are missing in requestValues'
            );
        }
    }

    /**
     * @param array<int, string> $groups
     */
    private function mapCustomerRoles(mixed $groups): void
    {
        foreach($groups as $group) {
            $subGroups = explode('/', $group);
            if (str_contains($subGroups[1], 'Beteiligung-Berechtigung')) {
                $subdomain = strtolower(explode('-', $subGroups[2])[0]);
                $this->customerRoleRelations[$subdomain][] = $subGroups[3];
            }
        }
    }

    public function __toString(): string
    {
        $customerRoleRelationString = '-';
        foreach ($this->customerRoleRelations as $subdomain => $roleNames) {
            $customerRoleRelationString = $subdomain.': ['.implode(', ', $roleNames).']';
        }

        return
            'userId: '.$this->userId.
            ', userName: '.$this->userName.
            ', fullName: '.$this->fullName.
            ', organisationId: '.$this->organisationId.
            ', organisationName: '.$this->organisationName.
            ', emailAddress: '.$this->emailAddress.
            ', roles: '.$customerRoleRelationString;
    }

}
