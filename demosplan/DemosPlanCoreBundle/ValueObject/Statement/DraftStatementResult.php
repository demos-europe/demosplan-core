<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method mixed getFilterSet()
 * @method mixed getResult()
 * @method mixed getSortingSet()
 * @method mixed getManuallySorted()
 * @method mixed getSearch()
 * @method mixed getTotal()
 */
class DraftStatementResult extends ValueObject
{
    public function __construct(protected $result, protected $filterSet, protected $sortingSet, protected $total, protected $search, protected $manuallySorted)
    {
        $this->lock();
    }
}
