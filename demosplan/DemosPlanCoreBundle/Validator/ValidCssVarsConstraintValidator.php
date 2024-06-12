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

use demosplan\DemosPlanCoreBundle\Constraint\ValidCssVarsConstraint;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
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
    private function validateTyped(?string $value, ValidCssVarsConstraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        // is it valid yml?
        try {
            $ymlParsedValues = Yaml::parse($value);
        } catch (ParseException) {
            $this->context->buildViolation($constraint->ymlExceptionMessage)->addViolation();

            return;
        }
        // is every variable on the list of allowed variables?
        $validAttributes = self::getValidCssVars();
        $ymlFormatConstranits = $this->getFormatConstraints();
        $violations = $this->context->getValidator()->validate($ymlParsedValues, $ymlFormatConstranits);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }
        foreach ($ymlParsedValues as $var => $val) {
            if (!in_array($var, $validAttributes, true)) {
                $this->context->buildViolation($constraint->invalidVarMessage)
                    ->setParameter('{{ var }}', $var)
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
            'link-alt', // Deprecated (has no effect anymore)
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

    /**
     * @return non-empty-list<Constraint>
     */
    private function getFormatConstraints(): array
    {
        return [
            new Assert\Type('array'),
            new Assert\All([
                new Assert\Type('string'),
                new Assert\NotNull(),
                new Assert\NotBlank(),
                new Assert\Regex('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i'),
            ]),
        ];
    }
}
