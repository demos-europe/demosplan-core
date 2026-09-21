<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateUsageRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Records that a specific boilerplate was inserted into the recommendation
 * of a specific segment. The mapping is static: it is neither updated nor
 * removed when the boilerplate or the recommendation text changes afterwards.
 */
#[ORM\Table(name: 'boilerplate_usage')]
#[UniqueConstraint(name: 'unique_boilerplate_segment', columns: ['boilerplate_id', 'segment_id'])]
#[ORM\Entity(repositoryClass: BoilerplateUsageRepository::class)]
class BoilerplateUsage implements UuidEntityInterface
{
    /**
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    protected $id;

    #[ORM\JoinColumn(name: 'boilerplate_id', referencedColumnName: '_pt_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Boilerplate::class, inversedBy: 'usages')]
    protected Boilerplate $boilerplate;

    #[ORM\JoinColumn(name: 'segment_id', referencedColumnName: '_st_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Segment::class)]
    protected Segment $segment;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
    protected ?DateTime $createDate = null;

    public function __construct(Boilerplate $boilerplate, Segment $segment)
    {
        $this->boilerplate = $boilerplate;
        $this->segment = $segment;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBoilerplate(): Boilerplate
    {
        return $this->boilerplate;
    }

    public function getSegment(): Segment
    {
        return $this->segment;
    }

    public function getCreateDate(): ?DateTime
    {
        return $this->createDate;
    }
}
