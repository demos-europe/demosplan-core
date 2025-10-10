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
 * @method string getAddressExtension()
 * @method string getCity()
 */
class OzgKeycloakUserData extends CommonUserData implements KeycloakUserDataInterface, Stringable
{
    private readonly string $keycloakGroupRoleString;
    private const COMPANY_STREET_ADDRESS = 'UnternehmensanschriftStrasse';
    private const COMPANY_ADDRESS_EXTENSION = 'UnternehmensanschriftAdressergaenzung';
    private const COMPANY_HOUSE_NUMBER = 'UnternehmensanschriftHausnummer';
    private const COMPANY_STREET_POSTAL_CODE = 'UnternehmensanschriftPLZ';
    private const COMPANY_CITY_ADDRESS = 'UnternehmensanschriftOrt';
    protected string $addressExtension = '';
    protected string $city = '';
    protected bool $isPrivatePerson = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
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

        $this->street = $userInformation[self::COMPANY_STREET_ADDRESS] ?? '';
        $this->addressExtension = $userInformation[self::COMPANY_ADDRESS_EXTENSION] ?? '';
        $this->houseNumber = $userInformation[self::COMPANY_HOUSE_NUMBER] ?? '';
        $this->postalCode = $userInformation[self::COMPANY_STREET_POSTAL_CODE] ?? '';
        $this->city = $userInformation[self::COMPANY_CITY_ADDRESS] ?? '';

        // Extract isPrivatePerson attribute from token
        $this->isPrivatePerson = isset($userInformation['isPrivatePerson'])
            && ('true' === $userInformation['isPrivatePerson'] || true === $userInformation['isPrivatePerson']);

        $this->lock();
        $this->checkMandatoryValuesExist();
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

    public function isPrivatePerson(): bool
    {
        return $this->isPrivatePerson;
    }

    /**
     * Override parent method to make roles optional when isPrivatePerson is true.
     * For private persons, roles will be assigned automatically to CITIZEN role,
     * so empty customerRoleRelations is acceptable.
     */
    public function checkMandatoryValuesExist(): void
    {
        // For private persons, temporarily set a dummy role to pass parent validation
        if ($this->isPrivatePerson) {
            $originalRoles = $this->customerRoleRelations;
            $this->customerRoleRelations = ['temp' => ['CITIZEN']];

            try {
                parent::checkMandatoryValuesExist();
            } finally {
                $this->customerRoleRelations = $originalRoles;
            }
        } else {
            // Non-private persons use standard validation
            parent::checkMandatoryValuesExist();
        }
    }

    public function __toString(): string
    {
        $parentString = parent::__toString();

        return $parentString.
            ', addressExtension: '.$this->addressExtension.
            ', city: '.$this->city.
            ', isPrivatePerson: '.($this->isPrivatePerson ? 'true' : 'false');
    }
}
