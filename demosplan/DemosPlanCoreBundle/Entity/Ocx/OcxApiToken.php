<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Ocx;

use demosplan\DemosPlanCoreBundle\Entity\User\AbstractApiToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OcxApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bearer token of an OCX sender. It acts as the technical user of the organisation whose procedures
 * the sender may deliver into.
 */
#[ORM\Entity(repositoryClass: OcxApiTokenRepository::class)]
#[ORM\Table(name: 'ocx_api_token')]
#[ORM\UniqueConstraint(name: 'ocx_api_token_prefix_unique', columns: ['token_prefix'])]
class OcxApiToken extends AbstractApiToken
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: '_u_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(User $user, Customer $customer, string $name, string $tokenPrefix, string $tokenHash)
    {
        $this->user = $user;
        $this->customer = $customer;
        $this->name = $name;
        $this->tokenPrefix = $tokenPrefix;
        $this->tokenHash = $tokenHash;
    }

    public static function literalPrefix(): string
    {
        return 'dplan_ocx_';
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
