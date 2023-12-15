<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\CorrectDateOrderConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if dates are given in correct order. If authoredDate is given, it should be
 * before the submitDate.
 *
 * @see CorrectDateOrderConstraint for usage as annotation
 */
class CorrectDateOrderConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof Statement) {
            throw new InvalidArgumentException('CorrectDateOrderConstraint validation currently possible on statements only');
        }

        if (!$constraint instanceof CorrectDateOrderConstraint) {
            throw new InvalidArgumentException('CorrectDateOrderConstraint was expected');
        }

        $authoredDate = $value->getMeta()->getAuthoredDate();
        if (is_int($authoredDate) && $authoredDate > $value->getSubmit()) {
            $externId = $value->getExternId();
            $this->context->buildViolation($constraint->message)
                ->atPath('submit')
                ->setParameter('{{ externId }}', $externId)
                ->addViolation();
        }
    }
}
