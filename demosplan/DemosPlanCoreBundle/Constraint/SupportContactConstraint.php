<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */
namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\SupportContactConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute]
class SupportContactConstraint extends Constraint
{
    public const NO_TITLE_MESSAGE = 'error.customer.support.contact.no.title';
    public const MISSING_CONTACT_MESSAGE = 'error.customer.support.contact.missing.contact.info';


    public function validatedBy(): string
    {
        return SupportContactConstraintValidator::class;
    }
}
