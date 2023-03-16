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
 * @method array  getRoles()
 * @method string getEmailAddress()
 * @method string getUserName()
 * @method string getUserId()
 * @method string getOrganisationName()
 * @method string getOrganisationId()
 * @method string getFullName()
 */
class BasicKeycloakResponse extends ValueObject implements KeycloakResponseInterface
{
    /**
     * @var array<int,string>
     */
    protected array $roles = [];

    /**
     * E-mail-address of the provided user.
     */
    protected string $emailAddress;

    /**
     * Unique abbreviation of chosen login name of the provided user.
     */
    protected string $userName;

    /**
     * Unique ID of the provided user.
     */
    protected string $userId;

    /**
     * Name of the provided organisation.
     */
    protected string $organisationName;

    /**
     * Unique identifier of the provided organisation.
     */
    protected string $organisationId;

    /**
     * Full (first- and last-) name of the provided user.
     */
    protected string $fullName;

    /**
     * @var string
     */
    protected mixed $customerName;

    public function create(ResourceOwnerInterface $resourceOwner): void
    {
        $keycloakResponseValues = $resourceOwner->toArray();

        if (array_key_exists('groups', $keycloakResponseValues)
            && is_array($keycloakResponseValues['groups'])
        ) {
            $this->mapOrganisations($keycloakResponseValues['groups']);
            $this->mapRoles($keycloakResponseValues['groups']);
        }

        $this->userId = $keycloakResponseValues['sub'] ?? '';
        $this->fullName = $keycloakResponseValues['name'] ?? '';
        $this->userName = $keycloakResponseValues['preferred_username'] ?? ''; //kind of "login" //has to be unique?
        $this->emailAddress = $keycloakResponseValues['email'] ?? '';

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

//        if ('' === $this->organisationId) {
//            $missingMandatoryValues[] = 'organisationId';
//        }
        if ('' === $this->organisationName) {
            $missingMandatoryValues[] = 'organisationName';
        }

        if ('' === $this->fullName) {
            $missingMandatoryValues[] = 'fullName';
        }

        if ('' === $this->roles) {
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
     *
     * @return void
     */
    private function mapRoles(mixed $groups): void
    {
        foreach($groups as $group) {
            $subGroups = explode('/', $group);
            if (str_contains($subGroups[1], 'Beteiligung-Berechtigung')) {
                $this->customerName = $subGroups[2]; //Mandant/Customer
                $this->roles[] = $subGroups[3]; //Role
            }
        }
    }

    /**
     * Todo: OrganistaionId is needed to ensure clear assignment even in case of rename an Organisation!
     *
     * @param array<int, string> $groups
     *
     * @return void
     */
    private function mapOrganisations(array $groups): void
    {
        foreach($groups as $group) {
            $subGroups = explode('/', $group);
            if (str_contains($subGroups[1], 'Beteiligung-Organisation')) {
                $this->organisationName = $subGroups[2]; //Organisation
            }
        }
    }
}
