<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;

abstract class CustomFieldValidator implements FieldTypeValidatorInterface
{
    protected const COMMON_CLASS_NAME_TO_CLASS_PATH_MAP = [
        'PROCEDURE'          => Procedure::class,
        'PROCEDURE_TEMPLATE' => Procedure::class,
        'SEGMENT'            => Segment::class,
        'STATEMENT'          => Statement::class,
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    abstract public function getSourceToTargetMapping(): array;

    public function supports(string $fieldType): bool
    {
        return $this->getFieldType() === $fieldType;
    }

    public function getClassNameToClassPathMap(): array
    {
        return static::COMMON_CLASS_NAME_TO_CLASS_PATH_MAP;
    }

    public function validate(array $attributes): void
    {
        $this->validateFieldType($attributes['fieldType']);
        $this->validateSourceToTargetMapping(
            $attributes['sourceEntity'],
            $attributes['targetEntity']
        );
        $this->validateSourceEntityIdExists(
            $attributes['sourceEntity'],
            $attributes['sourceEntityId']
        );
    }

    private function validateFieldType(?string $fieldType): void
    {
        if (!isset(CustomFieldInterface::TYPE_CLASSES[$fieldType])) {
            throw new InvalidArgumentException('Unknown custom field type: '.$fieldType);
        }
    }

    private function validateSourceToTargetMapping(?string $sourceEntity, ?string $targetEntity): void
    {
        $sourceToTargetMap = $this->getSourceToTargetMapping();
        if ($sourceToTargetMap[$sourceEntity] !== $targetEntity) {
            throw new InvalidArgumentException(sprintf('The target entity "%s" does not match the expected target entity "%s" for source entity "%s".', $targetEntity, $sourceToTargetMap[$sourceEntity], $sourceEntity));
        }
    }

    private function validateSourceEntityIdExists(string $sourceEntity, string $sourceEntityId): void
    {
        $classNameToClassPathMap = $this->getClassNameToClassPathMap();
        $sourceEntityClass = $classNameToClassPathMap[$sourceEntity];

        // Query the repository for the entity
        $repository = $this->entityManager->getRepository($sourceEntityClass);
        $entity = $repository->find($sourceEntityId);

        if (null === $entity) {
            throw new InvalidArgumentException(sprintf('The sourceEntityId "%s" was not found in the sourceEntity "%s".', $sourceEntityId, $sourceEntity));
        }
    }
}
