<?php

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;

class SingleSelectFieldValidator extends CustomFieldValidator
{
    private const FIELD_TYPE = 'singleSelect';

    protected const SOURCE_TO_TARGET_MAPPING = [
        'PROCEDURE'          => 'SEGMENT',
        'PROCEDURE_TEMPLATE' => 'SEGMENT',
    ];

    protected const classNameToClassPathtMap = [
            'PROCEDURE'          => Procedure::class,
            'PROCEDURE_TEMPLATE' => Procedure::class,
            'SEGMENT'            => Segment::class,
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
