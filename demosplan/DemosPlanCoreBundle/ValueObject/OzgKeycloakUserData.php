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
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
class OzgKeycloakUserData extends ValueObject implements KeycloakUserDataInterface, Stringable
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
    protected string $firstName = '';
    protected string $lastName = '';
    private readonly string $keycloakGroupRoleString;

    public function __construct(
        private readonly LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->keycloakGroupRoleString = $parameterBag->get('keycloak_group_role_string');
    }

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
        $this->firstName = $userInformation['given_name'] ?? '';
        $this->lastName = $userInformation['family_name'] ?? '';
        $this->userName = $userInformation['preferred_username'] ?? ''; // kind of "login" //has to be unique?
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

    /**
     * Mapping of roles of customer based on string-comparison.
     * Example of data structure of $groups:
     * [
     *      "/Beteiligung-Organisation/OrgaName1",
     *      "/Beteiligung-Berechtigung/CustomerName1/RoleName1",
     *      "/Beteiligung-Berechtigung/CustomerName1/RoleName2"
     * ].
     *
     * @param array<int, string> $groups
     */
    private function mapCustomerRoles(array $groups): void
    {
        foreach ($groups as $group) {
            $this->logger->info('Parse group: '.$group);
            $subGroups = explode('/', $group);
            if (str_contains($subGroups[1], $this->keycloakGroupRoleString)) {
                $subdomain = strtolower(explode('-', $subGroups[2])[0]);
                if (!array_key_exists(3, $subGroups)) {
                    $this->logger->error('Group does not contain role', ['group' => $group, 'subgroups' => $subGroups]);
                    continue;
                }
                $this->customerRoleRelations[$subdomain][] = $subGroups[3];
            }
        }
    }

    public function __toString(): string
    {
        $customerRoleRelationString = '';
        foreach ($this->customerRoleRelations as $subdomain => $roleNames) {
            $customerRoleRelationString .= $subdomain.': ['.implode(', ', $roleNames).'] ';
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
}
