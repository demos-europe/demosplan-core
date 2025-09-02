<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

class CustomFieldTypeValidatorRegistry
{
    /** @var FieldTypeValidatorInterface[] */
    private array $validators = [];

    public function __construct(iterable $validators = [])
    {
        foreach ($validators as $validator) {
            $this->addValidator($validator);
        }
    }

    public function addValidator(FieldTypeValidatorInterface $validator): void
    {
        $this->validators[$validator->getFieldType()] = $validator;
    }

    public function getValidatorForFieldType(string $fieldType): FieldTypeValidatorInterface
    {
        if (!isset($this->validators[$fieldType])) {
            throw new InvalidArgumentException("No validator found for field type: {$fieldType}");
        }

        return $this->validators[$fieldType];
    }

}
