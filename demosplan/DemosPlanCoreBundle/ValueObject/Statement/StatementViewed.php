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
 * @method string      getProcedureId()
 * @method array       getAccessMap()
 * @method string      getStatementId()
 */
class StatementViewed extends ValueObject
{
    public function __construct(
        protected string $procedureId,
        protected array $accessMap,
        protected string $statementId,
    ) {
        $this->lock();
    }
}
