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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\TextField;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Webmozart\Assert\Assert;

/**
 * Validates values for TextField.
 */
class TextFieldValueValidationStrategy implements CustomFieldValueValidationStrategyInterface
{
    public function supports(CustomFieldInterface $field): bool
    {
        return $field instanceof TextField;
    }

    public function validate(CustomFieldInterface $field, CustomFieldValue $customFieldValue): void
    {
        Assert::isInstanceOf($field, TextField::class);

        if ($field->getRequired() && (null === $customFieldValue->getValue() || '' === $customFieldValue->getValue())) {
            throw new InvalidArgumentException('Required text field must have a non-empty value');
        }

        if (null === $customFieldValue->getValue()) {
            return;
        }

        if (!is_string($customFieldValue->getValue())) {
            throw new InvalidArgumentException(sprintf('Text field value must be a string for CustomFieldId "%s"', $field->getId()));
        }
    }
}
