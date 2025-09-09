<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

class GetOriginalFileFromAnnotatedStatementEvent
{
    /**
     * @var File
     */
    private $file;

    public function __construct(
        /**
         * @param Statement
         */
        private readonly Statement $statement
    ) {
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function setFile(File $file): void
    {
        $this->file = $file;
    }

    public function getFile(): File
    {
        return $this->file;
    }
}
