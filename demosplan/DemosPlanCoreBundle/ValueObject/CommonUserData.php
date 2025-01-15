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

use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @method array  getCustomerRoleRelations()
 * @method string getEmailAddress()
 * @method string getUserName()
 * @method string getUserId()
 * @method string getOrganisationName()
 * @method string getOrganisationId()
 * @method string getFirstName()
 * @method string getLastName()
 */
class CommonUserData extends ValueObject
{
    protected string $firstName = '';
    protected string $lastName = '';
    /**
     * E-mail-address of the provided user.
     */
    protected string $emailAddress = '';
    /**
     * Name of the provided organisation.
     */
    protected string $organisationName = '';
    /**
     * Unique identifier of the provided organisation.
     */
    protected string $organisationId = '';
    /**
     * @var array<int, array<int,string>>
     */
    protected array $customerRoleRelations = [];
    /**
     * Unique abbreviation of chosen login name of the provided user.
     */
    protected string $userName = '';
    /**
     * Unique ID of the provided user.
     */
    protected string $userId = '';

    protected string $street = '';

    protected string $postalCode = '';


    public function __toString(): string
    {
        $customerRoleRelationString = '';
        foreach ($this->customerRoleRelations as $subdomain => $roleNames) {
            $customerRoleRelationString .= $subdomain.': ['.implode(
                ', ',
                $roleNames
            ).'] ';
        }

        return
            'userId: '.$this->userId.
            ', userName: '.$this->userName.
            ', firstName: '.$this->firstName.
            ', lastName: '.$this->lastName.
            ', organisationId: '.$this->organisationId.
            ', organisationName: '.$this->organisationName.
            ', emailAddress: '.$this->emailAddress.
            ', roles: '.$customerRoleRelationString;
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

        if ('' === $this->firstName && '' === $this->lastName) {
            $missingMandatoryValues[] = 'name';
        }

        if ([] === $this->customerRoleRelations) {
            $missingMandatoryValues[] = 'roles';
        }

        if ([] !== $missingMandatoryValues) {
            throw new AuthenticationCredentialsNotFoundException(implode(', ', $missingMandatoryValues).'are missing in requestValues');
        }
    }
}
