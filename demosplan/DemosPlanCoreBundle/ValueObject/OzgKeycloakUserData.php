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

/**
 * @method string getAddressExtension()
 * @method string getCity()
 */
class OzgKeycloakUserData extends CommonUserData implements KeycloakUserDataInterface, Stringable
{
    private readonly string $keycloakGroupRoleString;
    private readonly string $keycloakClientId;
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

    /**
     * Maps technical role codes from resource_access to readable role names.
     * This replaces the group-based role mapping for better integration with MUK.
     */
    private const TECHNICAL_ROLE_TO_READABLE = [
        'FP-A'  => 'Fachplanung Administration',
        'FP-PB' => 'Fachplanung Planungsbüro',
        'FP-SB' => 'Fachplanung Sachbearbeitung',
        'I-K'   => 'Institutions Koordination',
        'I-SB'  => 'Institutions Sachbearbeitung',
        'M-A'   => 'Mandanten Administration',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
    ) {
        $this->keycloakGroupRoleString = $parameterBag->get('keycloak_group_role_string');
        $this->keycloakClientId = $parameterBag->get('keycloak_client_id');
    }

    public function fill(ResourceOwnerInterface $resourceOwner, ?string $customerSubdomain = null): void
    {
        $userInformation = $resourceOwner->toArray();

        // Try to extract roles from resource_access claim first (preferred method)
        // Requires customerSubdomain parameter since resource_access can contain multiple clients
        $rolesExtracted = false;
        if (null !== $customerSubdomain
            && array_key_exists('resource_access', $userInformation)
            && is_array($userInformation['resource_access'])) {
            $rolesExtracted = $this->mapResourceAccessRoles($userInformation['resource_access'], $customerSubdomain);
        }

        // FALLBACK: If no roles were extracted from resource_access, use group-based extraction for backward compatibility
        if (!$rolesExtracted && array_key_exists('groups', $userInformation) && is_array($userInformation['groups'])) {
            $this->logger->info('No roles found in resource_access, falling back to group-based role extraction');
            $this->mapCustomerRoles($userInformation['groups']);
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
     * Extract roles from resource_access claim (preferred method).
     *
     * Example structure:
     * {
     *   "resource_access": {
     *     "diplan-develop-beteiligung-bau": {
     *       "roles": ["FP-SB", "I-K"]
     *     },
     *     "diplan-develop-beteiligung-rog": {
     *       "roles": ["FP-A"]
     *     }
     *   }
     * }
     *
     * @param array<string, mixed> $resourceAccess
     * @param string               $customerSubdomain The customer subdomain (e.g., 'hh', 'be', 'by') to store roles under
     *
     * @return bool True if roles were successfully extracted, false otherwise
     */
    private function mapResourceAccessRoles(array $resourceAccess, string $customerSubdomain): bool
    {
        // If no client ID is configured, skip resource_access extraction (fallback to groups)
        if ('' === $this->keycloakClientId) {
            $this->logger->info('No keycloak_client_id configured, skipping resource_access extraction');

            return false;
        }

        // Check if the configured client ID exists in resource_access
        if (!isset($resourceAccess[$this->keycloakClientId])) {
            $this->logger->warning("Configured client ID '{$this->keycloakClientId}' not found in resource_access");

            return false;
        }

        $clientData = $resourceAccess[$this->keycloakClientId];
        $this->logger->info("Found configured client '{$this->keycloakClientId}' in resource_access");

        if (!isset($clientData['roles']) || !is_array($clientData['roles'])) {
            $this->logger->warning("No roles array found in resource_access for client: {$this->keycloakClientId}");

            return false;
        }

        $rolesFound = false;
        foreach ($clientData['roles'] as $technicalRole) {
            if (array_key_exists($technicalRole, self::TECHNICAL_ROLE_TO_READABLE)) {
                $readableRoleName = self::TECHNICAL_ROLE_TO_READABLE[$technicalRole];
                $this->customerRoleRelations[$customerSubdomain][] = $readableRoleName;
                $rolesFound = true;
                $this->logger->info("Mapped technical role {$technicalRole} to {$readableRoleName} for customer subdomain {$customerSubdomain}");
            } else {
                $this->logger->warning("Unknown technical role in resource_access: {$technicalRole}");
            }
        }

        return $rolesFound;
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
