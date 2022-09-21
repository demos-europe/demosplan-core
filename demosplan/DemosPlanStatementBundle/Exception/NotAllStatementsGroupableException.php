<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Exception;

class NotAllStatementsGroupableException extends \Exception
{
    public static function create(): NotAllStatementsGroupableException
    {
        return new self('Not all statements to be grouped are groupable.');
    }

    public static function createForUnclaimed(): NotAllStatementsGroupableException
    {
        return new self('Not all statements or fragments to be grouped are claimed.');
    }

    public static function createFromStatementId(string $statementId): NotAllStatementsGroupableException
    {
        return new self("Statement not groupable: {$statementId}.");
    }
}
