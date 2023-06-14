<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Entities\SlugInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Faker\Provider\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table (uniqueConstraints={@UniqueConstraint(name="slug_unique", columns={"name"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\SlugRepository")
 */
class Slug extends CoreEntity implements UuidEntityInterface, SlugInterface
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
     * @var string
     *
     * @Assert\Length(min=0, max=255)
     *
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * Slug id. is generated.
     */
    public function __construct(string $name)
    {
        $this->id = Uuid::uuid();
        $this->setName($name);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $slugify = new Slugify();
        $this->name = $slugify->slugify($name);
    }
}
