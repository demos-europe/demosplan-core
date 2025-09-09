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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Constraint\MatchingSubmitTypesConstraint;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if the given submitType is being used in this project.
 *
 * @see MatchingSubmitTypesConstraint for usage as annotation
 */
class MatchingSubmitTypesConstraintValidator extends ConstraintValidator
{
    /**
     * @var array
     */
    private $transSubmitTypes;

    public function __construct(GlobalConfigInterface $config)
    {
        $formOptions = $config->getFormOptions();
        $this->transSubmitTypes = array_keys($formOptions['statement_submit_types']['values']);
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof Statement) {
            throw new InvalidArgumentException('MatchingSubmitTypesConstraint validation currently possible on statements only');
        }

        if (!$constraint instanceof MatchingSubmitTypesConstraint) {
            throw new InvalidArgumentException('MatchingSubmitTypesConstraint was expected');
        }

        $submitType = $value->getSubmitType();
        if (!in_array($submitType, $this->transSubmitTypes, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ invalidSubmitType }}', $submitType)
                ->atPath('submitType')
                ->addViolation();
        }
    }
}
