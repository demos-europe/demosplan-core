<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Validator\IsValidStatementAttachmentTypeValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class IsValidStatementAttachmentType extends Constraint
{
    public $message = 'Unsupported attachment type in statement attachment: {attachmentType}';

    public function validatedBy(): string
    {
        return IsValidStatementAttachmentTypeValidator::class;
    }
}
