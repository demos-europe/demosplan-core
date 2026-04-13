<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a group of statements (formerly "cluster head statement").
 *
 * A StatementGroup bundles one or more {@link StatementMember} instances under a
 * common head. It carries all attributes of a regular {@link Statement} plus an
 * optional human-readable name (inherited from {@link Statement::$name}).
 *
 * The members of the group are accessible via the inherited {@link Statement::getCluster()} collection.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementRepository")
 */
class StatementGroup extends Statement
{
}
