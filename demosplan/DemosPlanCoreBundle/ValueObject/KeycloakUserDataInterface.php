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

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @method array  getCustomerRoleRelations()
 * @method string getEmailAddress()
 * @method string getUserName()
 * @method string getUserId()
 * @method string getOrganisationName()
 * @method string getOrganisationId()
 * @method string getStreet()
 * @method string getPostalCode()
 * @method string getFirstName()
 * @method string getLastName()
 */
interface KeycloakUserDataInterface
{
    /**
     * @throws AuthenticationCredentialsNotFoundException in case of mandatory data is missing
     */
    public function fill(ResourceOwnerInterface $resourceOwner): void;

    /**
     * Checks for existing mandatory data.
     *
     * @throws AuthenticationCredentialsNotFoundException
     */
    public function checkMandatoryValuesExist(): void;

    public function __toString(): string;
}
