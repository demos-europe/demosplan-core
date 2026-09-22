<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class NotAllStatementsGroupableException extends Exception
{
    private ?string $statementId = null;

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
        $exception = new self("Statement not groupable: {$statementId}.");
        $exception->statementId = $statementId;

        return $exception;
    }

    /**
     * The id of the specific statement that could not be grouped, when known.
     */
    public function getStatementId(): ?string
    {
        return $this->statementId;
    }
}
