<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\ClaimedStatementIdsValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ClaimedStatementIdsConstraint extends Constraint
{
    /** @var string */
    protected $message = 'Statement not claimed.';

    public function validatedBy(): string
    {
        return ClaimedStatementIdsValidator::class;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
