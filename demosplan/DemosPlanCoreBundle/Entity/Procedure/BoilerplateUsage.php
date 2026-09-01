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
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateUsageRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Records that a specific boilerplate was inserted into the recommendation of a
 * specific Statement or Segment (Segment extends Statement via single table
 * inheritance — same pattern already used by {@see RecommendationVersion}). The
 * relation is live (DPLAN-18271): reconciled on every save by
 * {@see BoilerplateUsageReconciliationService}, not write-once.
 *
 * The join column name (`segment_id`) and unique constraint name stay as originally
 * created (DPLAN-18197) — the underlying FK already targets the shared `_statement`
 * table regardless of PHP-level typing here, so widening this property from `Segment`
 * to `Statement` needed no migration, only this rename.
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

    /**
     * Union type spelled out deliberately (rather than just `Statement`): documents,
     * right at the declaration, that a plain top-level Statement recommendation is just
     * as valid a target here as a Segment's — not something a reader has to already
     * know via the STI class hierarchy.
     */
    #[ORM\JoinColumn(name: 'segment_id', referencedColumnName: '_st_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Statement::class)]
    protected StatementInterface|SegmentInterface $statementOrSegment;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
    protected ?DateTime $createDate = null;

    public function __construct(Boilerplate $boilerplate, StatementInterface|SegmentInterface $statementOrSegment)
    {
        $this->boilerplate = $boilerplate;
        $this->statementOrSegment = $statementOrSegment;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBoilerplate(): Boilerplate
    {
        return $this->boilerplate;
    }

    public function getStatementOrSegment(): StatementInterface|SegmentInterface
    {
        return $this->statementOrSegment;
    }

    public function getCreateDate(): ?DateTime
    {
        return $this->createDate;
    }
}
