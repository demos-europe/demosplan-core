<?php declare(strict_types=1);


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
 * @method string getFullName()
 */
interface KeycloakResponseInterface
{
    /**
     * @throws AuthenticationCredentialsNotFoundException in case of mandatory data is missing.
     */
    public function create(ResourceOwnerInterface $resourceOwner): void;

    /**
     * Checks for existing mandatory data.
     * @throws AuthenticationCredentialsNotFoundException
     */
    public function checkMandatoryValuesExist(): void;
}
