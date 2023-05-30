<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class StatementNameTooLongException extends InvalidArgumentException
{
    /** @var int|null */
    protected $maxLength;
    /** @var int|null */
    protected $actualLength;

    public static function create(int $actualLength, int $maxLength): StatementNameTooLongException
    {
        $e = new self("The statement name must contain more than {$maxLength} characters. Actual length is {$actualLength}.");
        $e->maxLength = $maxLength;
        $e->actualLength = $actualLength;

        return $e;
    }

    /**
     * @return int|null
     */
    public function getMaxLength()
    {
        return $this->maxLength;
    }

    /**
     * @return int|null
     */
    public function getActualLength()
    {
        return $this->actualLength;
    }
}
