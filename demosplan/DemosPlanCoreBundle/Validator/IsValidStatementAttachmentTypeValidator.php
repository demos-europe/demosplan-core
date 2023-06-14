<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\IsValidStatementAttachmentType;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsValidStatementAttachmentTypeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(string $attachmentType, IsValidStatementAttachmentType $constraint): void
    {
        switch ($attachmentType) {
            case StatementAttachment::SOURCE_STATEMENT:
            case StatementAttachment::GENERIC:
                break;
            default:
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{attachmentType}', $attachmentType)
                    ->addViolation();
        }
    }
}
