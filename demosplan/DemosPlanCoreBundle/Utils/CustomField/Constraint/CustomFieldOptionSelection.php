<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint;

use demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator\CustomFieldOptionSelectionConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * For a selection of custom field options (`fieldId => [optionId, ...]`): every option has to belong to
 * the custom field it is selected for.
 */
class CustomFieldOptionSelection extends Constraint
{
    public string $message = 'Invalid selection for custom field "{{ field }}": {{ reason }}.';

    public function validatedBy(): string
    {
        return CustomFieldOptionSelectionConstraintValidator::class;
    }
}
