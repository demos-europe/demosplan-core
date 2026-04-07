<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Stores a full snapshot of a Statement/Segment recommendation at a specific version.
 *
 * Each time a recommendation is set or changed, a new entry is created with an
 * incremented version number. This allows viewing the exact recommendation text
 * at any historical version without replaying diffs.
 *
 * This is independent from EntityContentChange (which stores diffs) and
 * StatementVersionField (an older snapshot concept for Statement recommendations only).
 *
 * @ORM\Table(
 *     name="recommendation_version",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="unique_statement_version", columns={"statement_id", "version_number"})
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository")
 */
class RecommendationVersion extends CoreEntity implements UuidEntityInterface
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
     * @var StatementInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="recommendationVersions")
     *
     * @ORM\JoinColumn(name="statement_id", referencedColumnName="_st_id", nullable=false, onDelete="CASCADE")
     */
    protected $statement;

    /**
     * @var int
     *
     * @ORM\Column(name="version_number", type="integer", nullable=false, options={"unsigned":true})
     */
    protected $versionNumber = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="recommendation_text", type="text", nullable=false, length=15000000)
     */
    protected $recommendationText = '';

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdAt;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Only used for the virtual "current" version that is not persisted.
     * Persisted entities get their ID from the UUID generator.
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getStatement(): StatementInterface
    {
        return $this->statement;
    }

    public function setStatement(StatementInterface $statement): void
    {
        $this->statement = $statement;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): void
    {
        $this->versionNumber = $versionNumber;
    }

    public function getRecommendationText(): string
    {
        return $this->recommendationText;
    }

    public function setRecommendationText(string $recommendationText): void
    {
        $this->recommendationText = $recommendationText;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
