<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\EmailAddressInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Reasons for this entity:
 * - central place to use email address validations (eg. using annotations)
 * - getting all email addresses of all entities if needed (assumed the entity is actually used
 *   instead of a string field)
 * - if more logic is put into entity classes then email address related logic can be put here
 * - separate fields for local part and domain part possible (extendable)
 * - avoids using JSON (or doctrine arrays) in the database in case of to-many cardinalities
 * - normalization in general.
 *
 * Make sure to extend {@link EmailAddressRepository::deleteOrphanEmailAddresses} so that orphan
 * email addresses are removed to not keep personal data in the database without need.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository")
 */
class EmailAddress extends CoreEntity implements UuidEntityInterface, EmailAddressInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * The full email address containing the local part and domain name.
     * Not the so-called display name.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=254, nullable=false, unique=true)
     *
     *
     */
    #[Assert\NotBlank(allowNull: false)]
    #[Assert\Email(mode: 'strict')]
    protected $fullAddress;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getFullAddress(): string
    {
        return $this->fullAddress;
    }

    public function setFullAddress(string $fullAddress)
    {
        $this->fullAddress = $fullAddress;
    }
}
