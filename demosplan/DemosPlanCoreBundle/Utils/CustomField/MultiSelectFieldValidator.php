<?php

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValidator;

class MultiSelectFieldValidator extends CustomFieldValidator
{
    private const FIELD_TYPE = 'multiSelect';

    protected const SOURCE_TO_TARGET_MAPPING = [
        'PROCEDURE' => 'STATEMENT',
        'PROCEDURE_TEMPLATE' => 'STATEMENT',
    ];

    protected const classNameToClassPathtMap = [
        'PROCEDURE' => Procedure::class,
        'PROCEDURE_TEMPLATE' => Procedure::class,
        'STATEMENT' => Statement::class,
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

}
