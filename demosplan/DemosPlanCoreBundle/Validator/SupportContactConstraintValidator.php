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
        /**
         * In the future it is planned to create SupportContact entities independent of specific customers without
         * the need of a title. In that case the customer property will be null and the supportType will be
         * {@link SupportContact::SUPPORT_CONTACT_TYPE_PLATFORM}.
         * Otherwise - in customer context - a title is mandatory and the supportTypes are one of the other two constants
         * {@link SupportContact::SUPPORT_CONTACT_TYPE_DEFAULT}.
         * {@link SupportContact::SUPPORT_CONTACT_TYPE_CUSTOMER_LOGIN}.
         */
        $titleMissing = (null === $supportContact->getTitle() || '' === $supportContact->getTitle());
        if ($titleMissing
            && (null !== $supportContact->getCustomer()
                || SupportContact::SUPPORT_CONTACT_TYPE_PLATFORM !== $supportContact->getSupportType()
            )
        ) {
            $this->context->buildViolation($constraint::NO_TITLE_MESSAGE)
                ->addViolation();
        }

        // if a customer is set - the supportType can not be of type platform
        if (null !== $supportContact->getCustomer()
            && SupportContact::SUPPORT_CONTACT_TYPE_PLATFORM === $supportContact->getSupportType()
        ) {
            $this->context->buildViolation($constraint::WRONG_SUPPORT_TYPE)
                ->addViolation();
        }

        // Either an eMail address or a phone number has to be present.
        $phoneNumber = $supportContact->getPhoneNumber() ?? '';
        $emailAddress = $supportContact->getEMailAddress() ?? '';
        if ('' === $phoneNumber && '' === $emailAddress->getFullAddress()) {
            $this->context->buildViolation($constraint::MISSING_CONTACT_MESSAGE)
                ->addViolation();
        }
    }
}
