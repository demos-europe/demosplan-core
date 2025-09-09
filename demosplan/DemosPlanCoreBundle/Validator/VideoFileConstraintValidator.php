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

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Video;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class VideoFileConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value);
    }

    private function validateTyped(File $file): void
    {
        $validator = $this->context->getValidator();
        $violations = $validator->validate($file->getFilePathWithHash(), new \Symfony\Component\Validator\Constraints\File(
            null,
            '400Mi',
            null,
            Video::VALID_MIME_TYPES
        ));
        $violations->addAll($violations);
    }
}
