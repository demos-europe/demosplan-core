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

use EDT\JsonApi\OutputHandling\DynamicTransformer;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\Querying\Contracts\PathsBasedInterface;

use EDT\Wrapping\PropertyBehavior\Attribute\AttributeReadabilityInterface;
use EDT\Wrapping\ResourceBehavior\ResourceReadability;
use InvalidArgumentException;
use League\Fractal\Scope;
use Psr\Log\LoggerInterface;
use function in_array;
use const ARRAY_FILTER_USE_KEY;

/**
 * A custom transformer that always returns all attributes in the response,
 * regardless of their default status.
 *
 * This is useful for ensuring that all necessary fields are included in
 * API responses for newly created entities.
 *
 * @template TEntity of object
 * @template TCondition of PathsBasedInterface
 * @template TSorting of PathsBasedInterface
 * @extends DynamicTransformer<TEntity, TCondition, TSorting>
 */
class AllAttributesTransformer extends DynamicTransformer
{

    /**
    /**
     * @param non-empty-string $typeName
     * @param class-string<TEntity> $entityClass
     * @param ResourceReadability<TCondition, TSorting, TEntity> $readability
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        protected readonly string $typeName,
        protected readonly string $entityClass,
        protected readonly ResourceReadability $readability,
        protected readonly MessageFormatter $messageFormatter,
        protected readonly ?LoggerInterface $logger
    ) {
        parent::__construct($typeName, $entityClass, $readability, $messageFormatter, $logger);
    }

    /**
     * Override the parent method to return all attributes regardless of
     * their default status, unless specific fields were requested.
     *
     * @return array<non-empty-string, AttributeReadabilityInterface<TEntity>>
     */
    protected function getEffectiveAttributeReadabilities(Scope $scope): array
    {
        $fieldsetBag = $scope->getManager()->getFieldset($this->typeName);
        if (null === $fieldsetBag) {
            // If no fieldset was requested, return ALL attribute fields
            // Get attributes from the ResourceReadability which is accessible in this class
            return $this->readability->getAttributes();
        }

        // If specific fields were requested, handle them as normal
        $fieldset = iterator_to_array($fieldsetBag);
        return array_filter(
            $this->readability->getAttributes(),
            static fn (string $attributeName): bool => in_array($attributeName, $fieldset, true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
