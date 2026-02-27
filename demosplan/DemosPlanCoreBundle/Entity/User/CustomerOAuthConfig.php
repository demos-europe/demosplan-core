<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-customer Keycloak OAuth2 configuration.
 *
 * Customers without a record fall back to the global static keycloak_ozg client.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository")
 *
 * @ORM\Table(name="customer_oauth_config")
 */
class CustomerOAuthConfig extends CoreEntity implements UuidEntityInterface
{
    /**
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected string $id;

    /**
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="_c_id", nullable=false, onDelete="CASCADE", unique=true)
     */
    private CustomerInterface $customer;

    /**
     * @ORM\Column(name="keycloak_client_id", type="string", length=255, nullable=false)
     */
    private string $keycloakClientId;

    /**
     * @ORM\Column(name="keycloak_client_secret", type="string", length=255, nullable=false)
     */
    private string $keycloakClientSecret;

    /**
     * @ORM\Column(name="keycloak_auth_server_url", type="string", length=500, nullable=false)
     */
    private string $keycloakAuthServerUrl;

    /**
     * @ORM\Column(name="keycloak_realm", type="string", length=255, nullable=false)
     */
    private string $keycloakRealm;

    /**
     * Template for the Keycloak logout URL (e.g. with post_logout_redirect_uri and id_token_hint placeholders).
     * When null, falls back to the global oauth_keycloak_logout_route parameter.
     *
     * @ORM\Column(name="keycloak_logout_route", type="string", length=1000, nullable=true)
     */
    private ?string $keycloakLogoutRoute = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getKeycloakClientId(): string
    {
        return $this->keycloakClientId;
    }

    public function setKeycloakClientId(string $keycloakClientId): void
    {
        $this->keycloakClientId = $keycloakClientId;
    }

    public function getKeycloakClientSecret(): string
    {
        return $this->keycloakClientSecret;
    }

    public function setKeycloakClientSecret(string $keycloakClientSecret): void
    {
        $this->keycloakClientSecret = $keycloakClientSecret;
    }

    public function getKeycloakAuthServerUrl(): string
    {
        return $this->keycloakAuthServerUrl;
    }

    public function setKeycloakAuthServerUrl(string $keycloakAuthServerUrl): void
    {
        $this->keycloakAuthServerUrl = $keycloakAuthServerUrl;
    }

    public function getKeycloakRealm(): string
    {
        return $this->keycloakRealm;
    }

    public function setKeycloakRealm(string $keycloakRealm): void
    {
        $this->keycloakRealm = $keycloakRealm;
    }

    public function getKeycloakLogoutRoute(): ?string
    {
        return $this->keycloakLogoutRoute;
    }

    public function setKeycloakLogoutRoute(?string $keycloakLogoutRoute): void
    {
        $this->keycloakLogoutRoute = $keycloakLogoutRoute;
    }
}
