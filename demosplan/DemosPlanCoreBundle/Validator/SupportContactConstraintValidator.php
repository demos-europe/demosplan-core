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
        // In the future it is planned to create SupportContact entities independent of specific customers without
        // the need of a title. In that case the customer property will be null.
        // Otherwise - in customer context - a title is mandatory.
        $titleMissing = (null === $supportContact->getTitle() || '' === $supportContact->getTitle());
        if ($titleMissing && null !== $supportContact->getCustomer()) {
            $this->context->buildViolation($constraint::NO_TITLE_MESSAGE)
                ->addViolation();
        }

        // Either an eMail address or a phone number has to be present.
        $phoneNumber = $supportContact->getPhoneNumber() ?? '';
        $emailAddress = $supportContact->getEMailAddress() ?? '';
        if ('' === $phoneNumber &&  '' === $emailAddress->getFullAddress()) {
            $this->context->buildViolation($constraint::MISSING_CONTACT_MESSAGE)
                ->addViolation();
        }
    }
}
