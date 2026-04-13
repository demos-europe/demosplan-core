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
 * Represents a member statement inside a {@link StatementGroup} (formerly "cluster member statement").
 *
 * A StatementMember carries all attributes of a regular {@link Statement} and is
 * linked to its parent group via the inherited {@link Statement::$headStatement} relation.
 * The parent group is accessible via {@link Statement::getHeadStatement()}.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementRepository")
 */
class StatementMember extends Statement
{
}
