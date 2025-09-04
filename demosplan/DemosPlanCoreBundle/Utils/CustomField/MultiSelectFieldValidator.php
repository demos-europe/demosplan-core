<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

class MultiSelectFieldValidator extends CustomFieldValidator
{
    private const FIELD_TYPE = 'multiSelect';

    protected const SOURCE_TO_TARGET_MAPPING = [
        'PROCEDURE'          => 'STATEMENT',
        'PROCEDURE_TEMPLATE' => 'STATEMENT',
    ];

    protected const CLASS_NAME_TO_CLASS_PATH_MAP = [
        'PROCEDURE'          => Procedure::class,
        'PROCEDURE_TEMPLATE' => Procedure::class,
        'STATEMENT'          => Statement::class,
    ];

    public function supports(string $fieldType): bool
    {
        return self::FIELD_TYPE === $fieldType;
    }

    public function getFieldType(): string
    {
        return self::FIELD_TYPE;
    }

    public function getSourceToTargetMapping(): array
    {
        return self::SOURCE_TO_TARGET_MAPPING;
    }

    public function getClassNameToClassPathMap(): array
    {
        return self::CLASS_NAME_TO_CLASS_PATH_MAP;
    }
}
