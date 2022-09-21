<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Constraint\ValidCssVarsConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ValidCssVarsConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $this->validateTyped($value, $constraint);
    }

    /**
     * Incoming value is parsed to see if it is valid CSS or blank.
     */
    private function validateTyped(string $value, ValidCssVarsConstraint $constraint): void
    {
        if ('' === $value) {
            return;
        }

        // is it valid yml?
        try {
            $ymlParsedValues = Yaml::parse($value);
        } catch (ParseException $e) {
            $this->context->buildViolation($constraint->ymlExceptionMessage)->addViolation();
            return;
        }

        // is every variable on the list of allowed variables?
        $validAttributes = self::getValidCssVars();
        foreach ($ymlParsedValues as $var => $val) {
            if (!in_array($var, $validAttributes, true)) {
                $this->context->buildViolation($constraint->invalidVarMessage)
                    ->setParameter('{{ var }}', $var)
                    ->addViolation();
            }

            // Validate value to be cssColor
            if (7 !== strlen($val) || 0 !== strpos($val, '#')) {
                $this->context->buildViolation($constraint->invalidColorMessage)
                    ->setParameter('{{ color }}', $val)
                    ->addViolation();
            }
        }
    }

    public static function getValidCssVars(): array
    {
        return [
            'main',
            'main-contrast',
            'alt',
            'alt-contrast',
            'link',
            'link-hover',
            'link-active',
            'link-alt',
            'highlight',
            'cta',
            'cta-dark',
            'cta-light',
            'cta-contrast',
            'header-alt',
            'header-alt-contrast',
            'nav-alt-link',
            'nav-alt-link-hover',
            'nav-alt-alt',
            'nav-alt-bg',
            'nav',
            'nav-hover',
            'nav-current',
        ];
    }
}
