<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method array getFragmentIds()
 * @method       setFragmentIds(array $fragmentIds)
 * @method array getStatementIds()
 * @method       setStatementIds(array $statementIds)
 */
class IdCollection extends ValueObject
{
    protected array $fragmentIds;
    protected ?array $statementIds = null;
}
