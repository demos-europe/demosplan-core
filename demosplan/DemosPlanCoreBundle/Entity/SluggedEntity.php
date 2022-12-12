<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\MappedSuperclass */
abstract class SluggedEntity extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var Slug
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Slug")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    protected $currentSlug;

    /**
     * @var Collection Slug[]
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
     * Given a Slug object adds it to the Entity, updating its slugs history and sets it as current Slug.
     *
     * @return SluggedEntity
     */
    public function addSlug(Slug $slug)
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

    public function setCurrentSlug(Slug $currentSlug)
    {
        if (!$this->hasSlugString($currentSlug)) {
            throw new InvalidArgumentException('Slug '.$currentSlug->getName().'  must already exist in the Entity.');
        }
        $this->currentSlug = $currentSlug;
    }

    /**
     * Returns true if the Orga already had the received slug, false otherwise.
     */
    public function hasSlugString(Slug $slug): bool
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
