<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Grouping;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

/**
 * @template-extends EntityGrouper<Statement>
 */
class StatementEntityGrouper extends EntityGrouper
{
    protected function createEntityGroupInstance(string $title = ''): EntityGroupInterface
    {
        return new StatementEntityGroup($title);
    }
}
