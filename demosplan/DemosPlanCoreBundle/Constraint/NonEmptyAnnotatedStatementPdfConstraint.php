<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\NonEmptyAnnotatedStatementPdfConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class NonEmptyAnnotatedStatementPdfConstraint extends Constraint
{
    public $message = 'Statement property in AnnotatedStatementPdf can only be set if at least one page exists: {annotatedStatementPdf}';

    public function validatedBy(): string
    {
        return NonEmptyAnnotatedStatementPdfConstraintValidator::class;
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
