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
 * @method array  getRolleDiPlanBeteiligung()
 * @method string getEmailAddress()
 * @method string getUserName()
 * @method string getUserId()
 * @method string getOrganisationName()
 * @method string getOrganisationId()
 * @method string getFullName()
 */
class OzgKeycloakResponse extends ValueObject implements OzgKeycloakResponseInterface
{
    /**
     * @var array<int,string>
     */
    protected array $rolleDiPlanBeteiligung;

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

    public function __construct(ResourceOwnerInterface $resourceOwner)
    {
        $keycloakResponseValues = $resourceOwner->toArray();
        $this->rolleDiPlanBeteiligung = [];
        if (array_key_exists('rolleDiPlanBeteiligung', $keycloakResponseValues)
            && is_array($keycloakResponseValues['rolleDiPlanBeteiligung'])
        ) {
            $this->rolleDiPlanBeteiligung = $keycloakResponseValues['rolleDiPlanBeteiligung'];
        }

        $this->emailAddress = $keycloakResponseValues['emailAdresse'] ?? '';
        $this->userName = $keycloakResponseValues['nutzerId'] ?? '';
        $this->userId = $keycloakResponseValues['providerId'] ?? '';
        $this->organisationName = $keycloakResponseValues['verfahrenstraeger'] ?? '';
        $this->organisationId = $keycloakResponseValues['verfahrenstraegerGatewayId'] ?? '';
        $this->fullName = $keycloakResponseValues['vollerName'] ?? '';

        $this->lock();
        $this->checkMandatoryValuesExist();
    }

    /**
     * Checks for existing mandatory data.
     * @throws AuthenticationCredentialsNotFoundException
     */
    public function checkMandatoryValuesExist(): void
    {
        if ('' === $this->userName
            || '' === $this->userId
            || '' === $this->emailAddress
            || '' === $this->organisationId
            || '' === $this->fullName
        ) {
            throw new AuthenticationCredentialsNotFoundException('mandatory information missing in requestValues');
        }
    }
}
