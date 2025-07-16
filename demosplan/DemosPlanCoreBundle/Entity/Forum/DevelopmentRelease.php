<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Forum;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\DevelopmentReleaseInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 *  DevelopmentRelease.
 *
 * @ORM\Table(name="_progression_releases")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\DevelopmentReleaseRepository")
 */
class DevelopmentRelease extends CoreEntity implements UuidEntityInterface, DevelopmentReleaseInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_pr_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var string
     *
     * @ORM\Column(name="_pr_description", type="text", length=65535, nullable=true)
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="_pr_title", type="string", length=1024, nullable=false)
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="_pr_phase", type="string", length=128, nullable=false, options={"default":"configuration"})
     */
    protected $phase;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pr_start_date", type="datetime", nullable=true)
     */
    protected $startDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_pr_end_date", type="datetime", nullable=true)
     */
    protected $endDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_pr_modified_date", type="datetime", nullable=false)
     */
    protected $modifiedDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pr_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link DevelopmentRelease::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set text.
     *
     * @param string $description
     *
     * @return DevelopmentRelease
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return DevelopmentRelease
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set phase.
     *
     * @param string $phase
     *
     * @return DevelopmentRelease
     */
    public function setPhase($phase)
    {
        $this->phase = $phase;

        return $this;
    }

    /**
     * Get phase.
     *
     * @return string
     */
    public function getPhase()
    {
        return $this->phase;
    }

    /**
     * Set startDate.
     *
     * @param DateTime $startDate
     *
     * @return DevelopmentRelease
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Get startDate.
     *
     * @return DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set endDate.
     *
     * @param DateTime $endDate
     *
     * @return DevelopmentRelease
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Get endDate.
     *
     * @return DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Set modifiedDate.
     *
     * @param DateTime $modifiedDate
     *
     * @return DevelopmentRelease
     */
    public function setModifiedDate($modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * Get modifiedDate.
     *
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return DevelopmentRelease
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     *
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }
}
