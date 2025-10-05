<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\ExcelImporterPrePersistTagsEventInterface;

class ExcelImporterPrePersistTagsEvent implements ExcelImporterPrePersistTagsEventInterface
{
    public function __construct(protected array $segments = [], protected array $tags = [])
    {
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
