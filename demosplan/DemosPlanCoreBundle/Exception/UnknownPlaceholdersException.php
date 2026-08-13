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

/**
 * Thrown when a DOCX template contains placeholders that are not on the whitelist.
 * The catching boundary must translate the error message and present the placeholder
 * names to the user via the message bag.
 */
class UnknownPlaceholdersException extends InvalidStatementTemplateException
{
    /** @var list<string> */
    private array $unknownPlaceholders;

    /** @param list<string> $unknownPlaceholders */
    public function __construct(array $unknownPlaceholders)
    {
        parent::__construct('Template contains unknown placeholders.');
        $this->unknownPlaceholders = $unknownPlaceholders;
    }

    /** @return list<string> */
    public function getUnknownPlaceholders(): array
    {
        return $this->unknownPlaceholders;
    }
}
