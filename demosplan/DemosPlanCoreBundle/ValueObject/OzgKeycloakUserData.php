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
    private readonly string $keycloakClientId;
    private const COMPANY_STREET_ADDRESS = 'UnternehmensanschriftStrasse';
    private const COMPANY_ADDRESS_EXTENSION = 'UnternehmensanschriftAdressergaenzung';
    private const COMPANY_HOUSE_NUMBER = 'UnternehmensanschriftHausnummer';
    private const COMPANY_STREET_POSTAL_CODE = 'UnternehmensanschriftPLZ';
    private const COMPANY_CITY_ADDRESS = 'UnternehmensanschriftOrt';
    private const COMPANY_DEPARTMENT = 'Organisationseinheit';
    private const COMPANY_DEPARTMENT_EN = 'organisationUnit';
    private const IS_PRIVATE_PERSON = 'isPrivatePerson';
    private const RESPONSIBILITIES = 'responsibilities';

    protected string $addressExtension = '';
    protected string $city = '';
    protected string $companyDepartment = '';
    protected bool $isPrivatePerson = false;

    /**
     * Array of responsibilities from Keycloak token.
     * Each entry contains 'responsibility' (gwId) and optionally 'orgaName'.
     *
     * @var array<int, array{responsibility: string, orgaName?: string}>
     */
    protected array $responsibilities = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        private readonly RoleMapper $roleMapper,
    ) {
        $this->keycloakGroupRoleString = $parameterBag->get('keycloak_group_role_string');
        $this->keycloakClientId = $parameterBag->get('oauth_keycloak_client_id');
    }

    public function fill(ResourceOwnerInterface $resourceOwner, ?string $customerSubdomain = null): void
    {
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

        // Extract multiple responsibilities from token (multi-responsibility support)
        $this->parseResponsibilities($userInformation);

        $this->lock();
        $this->checkMandatoryValuesExist();
    }

    /**
     * Parse responsibilities from token.
     * Supports both multi-responsibility format and single organisationId.
     *
     * @param array<string, mixed> $userInformation
     */
    private function parseResponsibilities(array $userInformation): void
    {
        // Check for responsibilities array format (multi-org)
        if (array_key_exists(self::RESPONSIBILITIES, $userInformation)
            && is_array($userInformation[self::RESPONSIBILITIES])
            && [] !== $userInformation[self::RESPONSIBILITIES]
        ) {
            foreach ($userInformation[self::RESPONSIBILITIES] as $responsibilityData) {
                if (is_array($responsibilityData) && isset($responsibilityData['responsibility'])) {
                    $this->responsibilities[] = [
                        'responsibility' => (string) $responsibilityData['responsibility'],
                        'orgaName' => (string) ($responsibilityData['orgaName'] ?? $responsibilityData['responsibility']),
                    ];
                }
            }

            $this->logger->info('Parsed multiple responsibilities from token', [
                'count' => count($this->responsibilities),
                'responsibilities' => array_column($this->responsibilities, 'responsibility'),
            ]);

            return;
        }

        // Fallback: Use single organisationId as responsibility
        if ('' !== $this->organisationId) {
            $this->responsibilities[] = [
                'responsibility' => $this->organisationId,
                'orgaName' => '' !== $this->organisationName ? $this->organisationName : $this->organisationId,
            ];

            $this->logger->info('Using single organisationId as responsibility', [
                'responsibility' => $this->organisationId,
            ]);
        }
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
     * Get all responsibilities from the token.
     *
     * @return array<int, array{responsibility: string, orgaName: string}>
     */
    public function getResponsibilities(): array
    {
        return $this->responsibilities;
    }

    /**
     * Check if user has multiple responsibilities.
     */
    public function hasMultipleResponsibilities(): bool
    {
        return count($this->responsibilities) > 1;
    }

    /**
     * Get the primary (first) responsibility.
     * Returns null if no responsibilities exist.
     *
     * @return array{responsibility: string, orgaName: string}|null
     */
    public function getPrimaryResponsibility(): ?array
    {
        return $this->responsibilities[0] ?? null;
    }

    /**
     * Override parent method to support multi-responsibility tokens and private persons.
     */
    public function checkMandatoryValuesExist(): void
    {
        $missingValues = $this->checkUserIdentityFields();

        // Private persons: skip roles and organization validation
        if ($this->isPrivatePerson) {
            $this->throwIfMandatoryValuesMissing($missingValues);

            return;
        }

        // Multi-responsibility: organisationId optional if responsibilities present, but roles required
        if ([] !== $this->responsibilities) {
            if ([] === $this->customerRoleRelations) {
                $missingValues[] = 'roles';
            }
            $this->throwIfMandatoryValuesMissing($missingValues);

            return;
        }

        // Single-org: use standard validation
        parent::checkMandatoryValuesExist();
    }

    /**
     * Check common user identity fields (userId, userName, email, name).
     *
     * @return array<int, string> Missing field names
     */
    private function checkUserIdentityFields(): array
    {
        $missing = [];

        if ('' === $this->userId) {
            $missing[] = 'userId';
        }
        if ('' === $this->userName) {
            $missing[] = 'userName';
        }
        if ('' === $this->emailAddress) {
            $missing[] = 'emailAddress';
        }
        if ('' === $this->firstName && '' === $this->lastName) {
            $missing[] = 'name';
        }

        return $missing;
    }

    public function __toString(): string
    {
        $parentString = parent::__toString();

        $responsibilitiesString = implode(', ', array_column($this->responsibilities, 'responsibility'));

        return $parentString.
            ', addressExtension: '.$this->addressExtension.
            ', city: '.$this->city.
            ', company department: '.$this->companyDepartment.
            ', isPrivatePerson: '.($this->isPrivatePerson ? 'true' : 'false').
            ', responsibilities: ['.$responsibilitiesString.']';
    }
}
