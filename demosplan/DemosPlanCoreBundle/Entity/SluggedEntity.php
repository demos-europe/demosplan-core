<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\SluggedEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SlugInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\MappedSuperclass */
abstract class SluggedEntity extends CoreEntity implements UuidEntityInterface, SluggedEntityInterface
{
    /**
     * @var SlugInterface
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Slug")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    protected $currentSlug;

    /**
     * @var Collection SlugInterface[]
     *
     * @ORM\ManyToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Slug", cascade={"persist"})
     * @ORM\JoinTable(
     *     name="entity_slugs_doctrine",
     *     joinColumns={@ORM\JoinColumn(name="entity_id", referencedColumnName="_entity_id", onDelete="RESTRICT")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="s_id", referencedColumnName="id", onDelete="RESTRICT")}
     * )
     */
    protected $slugs;

    public function getSlugs(): Collection
    {
        return $this->slugs;
    }

    public function setSlugs(Collection $slugs)
    {
        $this->slugs = $slugs;
    }

    /**
     * @return SluggedEntity
     */
    public function addSlug(SlugInterface $slug)
    {
        $slugs = $this->slugs;
        $slugs[] = $slug;
        $this->setSlugs($slugs);
        $this->setCurrentSlug($slug);

        return $this;
    }

    public function getCurrentSlug(): Slug
    {
        return $this->currentSlug;
    }

    public function setCurrentSlug(SlugInterface $currentSlug)
    {
        if (!$this->hasSlugString($currentSlug)) {
            throw new InvalidArgumentException('Slug '.$currentSlug->getName().'  must already exist in the Entity.');
        }
        $this->currentSlug = $currentSlug;
    }

    public function hasSlugString(SlugInterface $slug): bool
    {
        return $this->getSlugs()->map(function (Slug $slug) {
            return $slug->getName();
        })->contains($slug->getName());
    }

    public function isSlugCurrent(string $slug): bool
    {
        return $this->getCurrentSlug()->getName() === $slug;
    }

    public function setInitialSlug()
    {
        $slug = new Slug($this->getId());
        $this->currentSlug = $slug;
        $this->slugs = [$slug];
    }
}
