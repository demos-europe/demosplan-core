<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

class GetOriginalFileFromAnnotatedStatementEvent
{
    /**
     * @param File
     */
    private $file = null;

    /**
     * @param Statement
     */
    private $statement;

    public function __construct(Statement $statement)
    {
        $this->statement = $statement;
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function setFile(File $file)
    {
        $this->file = $file;
    }

    public function getFile()
    {
        return $this->file;
    }
}
