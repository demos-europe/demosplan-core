<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint\CustomFieldOptionSelection;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * The field type does not matter, each field answers for its own options.
 */
class CustomFieldOptionSelectionConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly CustomFieldProvider $customFieldProvider)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomFieldOptionSelection) {
            throw new UnexpectedTypeException($constraint, CustomFieldOptionSelection::class);
        }

        if (null === $value || [] === $value) {
            return;
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        // PHP turns numeric array keys into integers, but the lookup expects string ids.
        $fieldIds = array_map('strval', array_keys($value));
        $customFields = $this->customFieldProvider->getCustomFieldsByIds($fieldIds);

        foreach ($value as $fieldId => $optionIds) {
            $customField = $customFields[$fieldId] ?? null;

            try {
                if (null === $customField) {
                    throw new InvalidArgumentException('The custom field does not exist');
                }
                $customField->assertHasOptions($optionIds);
            } catch (InvalidArgumentException $exception) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ field }}', (string) $fieldId)
                    ->setParameter('{{ reason }}', $exception->getMessage())
                    ->addViolation();
            }
        }
    }
}
