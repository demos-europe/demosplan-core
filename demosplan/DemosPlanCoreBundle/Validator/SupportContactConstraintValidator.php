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

use demosplan\DemosPlanCoreBundle\Constraint\SupportContactConstraint;
use demosplan\DemosPlanCoreBundle\Entity\User\SupportContact;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SupportContactConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    private function validateTyped(SupportContact $supportContact, SupportContactConstraint $constraint): void
    {
        // It is planned to create SupportContact entities for all customers in the future without the need of a title.
        // in that case the customer property will be null
        // otherwise - in customer context - a title is mandatory.
        $titleMissingButHasToBePresent = null !== $supportContact->getCustomer()
            && (null === $supportContact->getTitle() || '' === $supportContact->getTitle());
        if ($titleMissingButHasToBePresent) {
            $this->context->buildViolation($constraint::NO_TITLE_MESSAGE)
                ->addViolation();
        }
        // either an eMail address or a phone number has to be present.
        $contactInfoIsMissing = (null === $supportContact->getPhoneNumber() || '' === $supportContact->getPhoneNumber())
            && (null === $supportContact->getEMailAddress() || '' === $supportContact->getEMailAddress());
        if ($contactInfoIsMissing) {
            $this->context->buildViolation($constraint::MISSING_CONTACT_MESSAGE)
                ->addViolation();
        }
    }
}
