<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Logic\StatementSynchronizer;
use Tests\Base\FunctionalTestCase;

class StatementSynchronizerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(StatementSynchronizer::class);
    }
}
