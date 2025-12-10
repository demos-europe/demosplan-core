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

use Stringable;
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
 * @method string getPostalCode()
 * @method string getStreet()
 * @method string getHouseNumber()
 * @method string getCompanyDepartment()
 */
class CommonUserData extends ValueObject implements Stringable
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
    protected string $houseNumber = '';
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
            ', roles: '.$customerRoleRelationString.
            ', street: '.$this->street.
            ', houseNumber: '.$this->houseNumber.
            ', postalCode: '.$this->postalCode;
    }

    /**
     * Checks common mandatory values (userId, userName, email, organisationId, name).
     * This method is extracted to allow child classes to validate only common fields
     * without requiring role validation.
     *
     * @return array<int, string> Array of missing mandatory field names
     */
    protected function checkCommonMandatoryValues(): array
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

        return $missingMandatoryValues;
    }

    /**
     * Throws an exception if any mandatory values are missing.
     *
     * @param array<int, string> $missingMandatoryValues Array of missing field names
     *
     * @throws AuthenticationCredentialsNotFoundException if any values are missing
     */
    protected function throwIfMandatoryValuesMissing(array $missingMandatoryValues): void
    {
        if ([] !== $missingMandatoryValues) {
            throw new AuthenticationCredentialsNotFoundException(implode(', ', $missingMandatoryValues).' are missing in requestValues');
        }
    }

    /**
     * Checks for existing mandatory data including roles.
     */
    public function checkMandatoryValuesExist(): void
    {
        $missingMandatoryValues = $this->checkCommonMandatoryValues();

        if ([] === $this->customerRoleRelations) {
            $missingMandatoryValues[] = 'roles';
        }

        $this->throwIfMandatoryValuesMissing($missingMandatoryValues);
    }
}
