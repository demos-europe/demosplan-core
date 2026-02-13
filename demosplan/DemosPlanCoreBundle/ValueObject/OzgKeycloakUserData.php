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
    private const ORGANISATION_AFFILIATIONS = 'organisation';

    protected string $addressExtension = '';
    protected string $city = '';
    protected string $companyDepartment = '';
    protected bool $isPrivatePerson = false;

    /**
     * Array of affiliations (organisational units) from Keycloak token.
     * Parsed from the 'organisation' array field in the token.
     *
     * @var array<int, array{id: string, name: string}>
     */
    protected array $affiliations = [];

    /**
     * Array of responsibilities (functional areas) from Keycloak token.
     * Each entry contains 'id' and 'name'.
     *
     * @var array<int, array{id: string, name: string}>
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

        // Extract affiliations and responsibilities from token (multi-organisation support)
        $this->parseAffiliations($userInformation);
        $this->parseResponsibilities($userInformation);

        // Fallback: if neither affiliations nor responsibilities arrays are present,
        // convert single organisationId into an affiliation
        if ([] === $this->affiliations && [] === $this->responsibilities && '' !== $this->organisationId) {
            $this->affiliations[] = [
                'id'   => $this->organisationId,
                'name' => '' !== $this->organisationName ? $this->organisationName : $this->organisationId,
            ];
        }

        $this->lock();
        $this->checkMandatoryValuesExist();
    }

    /**
     * Parse affiliations (organisational units) from the 'organisation' field in the token.
     *
     * @param array<string, mixed> $userInformation
     */
    private function parseAffiliations(array $userInformation): void
    {
        if (!array_key_exists(self::ORGANISATION_AFFILIATIONS, $userInformation)
            || !is_array($userInformation[self::ORGANISATION_AFFILIATIONS])) {
            return;
        }

        foreach ($userInformation[self::ORGANISATION_AFFILIATIONS] as $data) {
            if (is_array($data) && isset($data['id'])) {
                $this->affiliations[] = [
                    'id'   => (string) $data['id'],
                    'name' => (string) ($data['name'] ?? $data['id']),
                ];
            }
        }

        if ([] !== $this->affiliations) {
            $this->logger->info('Parsed affiliations from token', [
                'count' => count($this->affiliations),
                'affiliations' => array_column($this->affiliations, 'id'),
            ]);
        }
    }

    /**
     * Parse responsibilities (functional areas) from the 'responsibilities' field in the token.
     *
     * @param array<string, mixed> $userInformation
     */
    private function parseResponsibilities(array $userInformation): void
    {
        if (!array_key_exists(self::RESPONSIBILITIES, $userInformation)
            || !is_array($userInformation[self::RESPONSIBILITIES])) {
            return;
        }

        foreach ($userInformation[self::RESPONSIBILITIES] as $data) {
            if (is_array($data) && isset($data['id'])) {
                $this->responsibilities[] = [
                    'id'   => (string) $data['id'],
                    'name' => (string) ($data['name'] ?? $data['id']),
                ];
            }
        }

        if ([] !== $this->responsibilities) {
            $this->logger->info('Parsed responsibilities from token', [
                'count' => count($this->responsibilities),
                'responsibilities' => array_column($this->responsibilities, 'id'),
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
     * Get all affiliations (organisational units) from the token.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getAffiliations(): array
    {
        return $this->affiliations;
    }

    /**
     * Check if affiliations are present in the token.
     */
    public function hasAffiliations(): bool
    {
        return [] !== $this->affiliations;
    }

    /**
     * Get all responsibilities (functional areas) from the token.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getResponsibilities(): array
    {
        return $this->responsibilities;
    }

    /**
     * Check if the cartesian product of affiliations × responsibilities yields more than one organisation.
     * Organisation (affiliations) is always >= 1, responsibilities is 0..n.
     */
    public function hasMultipleOrganisations(): bool
    {
        $affiliationCount = count($this->affiliations);
        $responsibilityCount = count($this->responsibilities);

        if ($responsibilityCount > 0) {
            return ($affiliationCount * $responsibilityCount) > 1;
        }

        return $affiliationCount > 1;
    }

    /**
     * Override parent method to support multi-organisation tokens and private persons.
     */
    public function checkMandatoryValuesExist(): void
    {
        $missingValues = $this->checkUserIdentityFields();

        // Private persons: skip roles and organization validation
        if ($this->isPrivatePerson) {
            $this->throwIfMandatoryValuesMissing($missingValues);

            return;
        }

        // Multi-organisation: organisationId optional if affiliations or responsibilities present, but roles required
        if ([] !== $this->affiliations || [] !== $this->responsibilities) {
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

        $affiliationsString = implode(', ', array_column($this->affiliations, 'id'));
        $responsibilitiesString = implode(', ', array_column($this->responsibilities, 'id'));

        return $parentString.
            ', addressExtension: '.$this->addressExtension.
            ', city: '.$this->city.
            ', company department: '.$this->companyDepartment.
            ', isPrivatePerson: '.($this->isPrivatePerson ? 'true' : 'false').
            ', affiliations: ['.$affiliationsString.']'.
            ', responsibilities: ['.$responsibilitiesString.']';
    }
}
