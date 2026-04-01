<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A StatementGroup groups multiple statements that express the same concern ("gleich lautend").
 *
 * It is a Single Table Inheritance subtype of Statement. All group-specific columns are nullable
 * in the shared _statement table so that regular Statement and Segment rows are not affected.
 *
 * All existing code that calls isClusterStatement() continues to work without changes via the
 * override below. The @deprecated marker signals that new code should use instanceof instead.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementGroupRepository")
 */
class StatementGroup extends Statement
{
    /**
     * Optional display name for the group, shown in the STN liste.
     *
     * @var string|null
     *
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    private ?string $groupName = null;

    /**
     * The member statement used for sorting by externId in the STN liste.
     * User can change this at any time. Nullable because the column must be NULL
     * for all non-group rows in the shared _statement table.
     *
     * @var Statement|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement")
     *
     * @ORM\JoinColumn(name="group_representative_id", referencedColumnName="_st_id", nullable=true, onDelete="SET NULL")
     */
    private ?Statement $representative = null;

    /**
     * Timestamp when this group was created. Nullable because the column must be NULL
     * for all non-group rows in the shared _statement table.
     *
     * @var DateTimeImmutable|null
     *
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private ?DateTimeImmutable $groupCreatedAt = null;

    public function __construct()
    {
        parent::__construct();

        // Set the inherited cluster_statement column to true so that all existing DQL filters
        // (e.g. `statement.clusterStatement = false`) correctly exclude StatementGroup rows
        // from regular statement lists without any changes to those filters.
        $this->clusterStatement = true;

        $this->groupCreatedAt = new DateTimeImmutable();
    }

    /**
     * @deprecated use instanceof StatementGroup instead — always returns true by definition
     */
    public function isClusterStatement(): bool
    {
        return true;
    }

    /**
     * No-op: a StatementGroup is always a cluster statement by definition.
     * Exists so that all existing setClusterStatement() call sites keep compiling
     * without changes during the migration to instanceof checks.
     */
    public function setClusterStatement($isCluster): Statement
    {
        return $this;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(?string $groupName): self
    {
        $this->groupName = $groupName;

        return $this;
    }

    public function getRepresentative(): ?Statement
    {
        return $this->representative;
    }

    public function setRepresentative(?Statement $representative): self
    {
        $this->representative = $representative;

        return $this;
    }

    public function getGroupCreatedAt(): ?DateTimeImmutable
    {
        return $this->groupCreatedAt;
    }

    /**
     * Alias for getCluster(). Group members are tracked via the inherited headStatement FK
     * so the assessment table cluster feature keeps working without changes.
     */
    public function getMembers(): Collection
    {
        return $this->getCluster();
    }
}
