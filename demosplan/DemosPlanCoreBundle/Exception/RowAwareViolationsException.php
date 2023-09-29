<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class RowAwareViolationsException extends ViolationsException
{
    /**
     * @var int
     */
    private $row;

    public static function fromRowAndConstraintViolationList(int $row, ConstraintViolationListInterface $violations): self
    {
        /** @var RowAwareViolationsException $instance */
        $instance = self::fromConstraintViolationList($violations);
        $instance->row = $row;

        return $instance;
    }

    public function getRow(): int
    {
        return $this->row;
    }
}
