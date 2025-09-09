<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Documents;

use demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory\DataGeneratorInterface;

abstract class FakeDocumentGenerator implements DataGeneratorInterface
{
    public function generate(int $approximateSizeInBytes): string
    {
        return random_bytes($approximateSizeInBytes);
    }
}
