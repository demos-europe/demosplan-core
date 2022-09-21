<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

class ProcedurePublicationException extends InvalidArgumentException
{
    public static function procedureNotFound(string $procedureId): ProcedurePublicationException
    {
        return new self("No procedure found for ID {$procedureId}");
    }

    public static function publicationNotAllowed(string $procedureId): ProcedurePublicationException
    {
        return new self("No publishing allowed due to settings in procedure with ID {$procedureId}");
    }
}
