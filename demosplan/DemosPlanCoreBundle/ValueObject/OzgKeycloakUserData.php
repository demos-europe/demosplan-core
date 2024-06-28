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
use Stringable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OzgKeycloakUserData extends CommonUserData implements KeycloakUserDataInterface, Stringable
{
    private readonly string $keycloakGroupRoleString;

    public function __construct(ParameterBagInterface $parameterBag)
    {
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
            $subGroups = explode('/', $group);
            if (str_contains($subGroups[1], $this->keycloakGroupRoleString)) {
                $subdomain = strtolower(explode('-', $subGroups[2])[0]);
                $this->customerRoleRelations[$subdomain][] = $subGroups[3];
            }
        }
    }
}
