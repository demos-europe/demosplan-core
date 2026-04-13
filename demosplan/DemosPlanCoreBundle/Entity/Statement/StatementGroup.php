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

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A StatementGroup is a virtual "head" statement that groups identical statements
 * ("gleich lautend") in the new STN list.
 *
 * It extends Statement via Single Table Inheritance — no separate DB table is needed.
 * The discriminator column entity_type in _statement is set to "StatementGroup".
 *
 * The constructor sets clusterStatement=true directly so that all existing DQL filters
 * (e.g. `statement.clusterStatement = false`) continue to work without modification.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementGroupRepository")
 */
class StatementGroup extends Statement
{
    /**
     * Optional display name for the group.
     *
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    private ?string $groupName = null;

    /**
     * The statement whose content was copied to represent this group.
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement")
     *
     * @ORM\JoinColumn(name="group_representative_id", referencedColumnName="_st_id", nullable=true, onDelete="SET NULL")
     */
    private ?Statement $representative = null;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private ?DateTimeImmutable $groupCreatedAt = null;

    public function __construct()
    {
        parent::__construct();
        // Set the DB column directly — property is protected in Statement.
        // Without this, cluster_statement=0 would slip into the DB and break
        // existing queries that filter group heads via clusterStatement=true.
        $this->clusterStatement = true;
        $this->groupCreatedAt = new DateTimeImmutable();
    }

    /**
     * @deprecated Use instanceof StatementGroup instead
     */
    public function isClusterStatement(): bool
    {
        return true;
    }

    /**
     * No-op — a StatementGroup is always a cluster head; this flag must not change.
     *
     * @param bool $isCluster
     */
    public function setClusterStatement($isCluster): Statement
    {
        return $this;
    }

    /**
     * Alias for the inherited $cluster collection.
     * Returns the statements that have this group as their headStatement.
     */
    public function getMembers(): Collection
    {
        return $this->getCluster();
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
}
