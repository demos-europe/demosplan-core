<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Logic\OzyKeycloakDataMapper\RoleMapper;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method string getAddressExtension()
 * @method string getCity()
 */
class OzgKeycloakUserData extends CommonUserData implements KeycloakUserDataInterface, Stringable
{
    private const RESOURCE_ACCESS = 'resource_access';
    private const GROUPS = 'groups';
    private readonly string $keycloakGroupRoleString;
    private readonly string $defaultKeycloakClientId;
    private string $keycloakClientId;
    private const COMPANY_STREET_ADDRESS = 'UnternehmensanschriftStrasse';
    private const COMPANY_ADDRESS_EXTENSION = 'UnternehmensanschriftAdressergaenzung';
    private const COMPANY_HOUSE_NUMBER = 'UnternehmensanschriftHausnummer';
    private const COMPANY_STREET_POSTAL_CODE = 'UnternehmensanschriftPLZ';
    private const COMPANY_CITY_ADDRESS = 'UnternehmensanschriftOrt';
    private const COMPANY_DEPARTMENT = 'Organisationseinheit';
    private const COMPANY_DEPARTMENT_EN = 'organisationUnit';
    private const IS_PRIVATE_PERSON = 'isPrivatePerson';

    protected string $addressExtension = '';
    protected string $city = '';
    protected string $companyDepartment = '';
    protected bool $isPrivatePerson = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        private readonly RoleMapper $roleMapper,
    ) {
        $this->keycloakGroupRoleString = $parameterBag->get('keycloak_group_role_string');
        $this->defaultKeycloakClientId = $parameterBag->get('oauth_keycloak_client_id');
        $this->keycloakClientId = $this->defaultKeycloakClientId;
    }

    public function fill(
        ResourceOwnerInterface $resourceOwner,
        ?string $customerSubdomain = null,
        ?string $keycloakClientId = null,
    ): void {
        // Always reset to global default, then override if a per-customer ID is provided.
        // This prevents stale state from a previous request in long-running processes.
        $this->keycloakClientId = $keycloakClientId ?? $this->defaultKeycloakClientId;

        $userInformation = $resourceOwner->toArray();

        // Try to extract roles from resource_access claim first (preferred method)
        // Requires customerSubdomain parameter since resource_access can contain multiple clients
        if (null !== $customerSubdomain
            && array_key_exists(self::RESOURCE_ACCESS, $userInformation)
            && is_array($userInformation[self::RESOURCE_ACCESS])) {
            $mappedRoles = $this->roleMapper->mapResourceAccessRoles(
                $userInformation[self::RESOURCE_ACCESS],
                $this->keycloakClientId,
                $customerSubdomain
            );

            if ([] !== $mappedRoles) {
                $this->customerRoleRelations = $mappedRoles;
            }
        }

        // FALLBACK: If no roles were extracted from resource_access, use group-based extraction for backward compatibility
        if ([] === $this->customerRoleRelations && array_key_exists(self::GROUPS, $userInformation) && is_array($userInformation[self::GROUPS])) {
            $this->logger->info('No roles found in resource_access, falling back to group-based role extraction');
            $this->mapCustomerRoles($userInformation[self::GROUPS]);
        }

        $this->userId = $userInformation['sub'] ?? '';
        $this->organisationName = $userInformation['organisationName'] ?? '';
        $this->organisationId = $userInformation['organisationId'] ?? '';
        $this->firstName = $userInformation['given_name'] ?? '';
        $this->lastName = $userInformation['family_name'] ?? '';
        $this->userName = $userInformation['preferred_username'] ?? ''; // kind of "login" //has to be unique?
        $this->emailAddress = $userInformation['email'] ?? '';

        $this->street = $userInformation[self::COMPANY_STREET_ADDRESS] ?? '';
        $this->addressExtension = $userInformation[self::COMPANY_ADDRESS_EXTENSION] ?? '';
        $this->houseNumber = $userInformation[self::COMPANY_HOUSE_NUMBER] ?? '';
        $this->postalCode = $userInformation[self::COMPANY_STREET_POSTAL_CODE] ?? '';
        $this->city = $userInformation[self::COMPANY_CITY_ADDRESS] ?? '';
        $this->companyDepartment = $userInformation[self::COMPANY_DEPARTMENT] ?? $userInformation[self::COMPANY_DEPARTMENT_EN] ?? '';

        // Extract isPrivatePerson attribute from token
        $this->isPrivatePerson = isset($userInformation[self::IS_PRIVATE_PERSON])
            && ('true' === $userInformation[self::IS_PRIVATE_PERSON] || true === $userInformation[self::IS_PRIVATE_PERSON]);

        $this->lock();
        $this->checkMandatoryValuesExist();
    }

    /**
     * Mapping of roles of customer based on string-comparison (LEGACY fallback method).
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

    public function isPrivatePerson(): bool
    {
        return $this->isPrivatePerson;
    }

    /**
     * Override parent method to make roles and organization optional when isPrivatePerson is true.
     *
     * For private persons authenticated via the isPrivatePerson token attribute:
     * - Roles are assigned automatically (CITIZEN role), so customerRoleRelations may be empty
     * - Organization attributes are optional, as they'll be assigned to the private organization
     * - Only validates: userId, userName, emailAddress, and name (firstName/lastName)
     *
     * For organization users, standard validation including roles and organization is performed.
     */
    public function checkMandatoryValuesExist(): void
    {
        if ($this->isPrivatePerson) {
            // For private persons, validate only essential fields (skip roles and organization)
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

            if ('' === $this->firstName && '' === $this->lastName) {
                $missingMandatoryValues[] = 'name';
            }

            $this->throwIfMandatoryValuesMissing($missingMandatoryValues);
        } else {
            // Organization users: use standard validation including role and organization checks
            parent::checkMandatoryValuesExist();
        }
    }

    public function __toString(): string
    {
        $parentString = parent::__toString();

        return $parentString.
            ', addressExtension: '.$this->addressExtension.
            ', city: '.$this->city.
            ', company department: '.$this->companyDepartment.
            ', isPrivatePerson: '.($this->isPrivatePerson ? 'true' : 'false');
    }
}
