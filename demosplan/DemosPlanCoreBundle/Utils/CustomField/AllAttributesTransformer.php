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
use EDT\JsonApi\OutputHandling\DynamicTransformer;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeReadabilityInterface;
use EDT\Wrapping\ResourceBehavior\ResourceReadability;
use InvalidArgumentException;
use League\Fractal\Scope;
use Psr\Log\LoggerInterface;

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
 *
 * @extends DynamicTransformer<TEntity, TCondition, TSorting>
 */
class AllAttributesTransformer extends DynamicTransformer
{
    /**
     * @param non-empty-string                                   $typeName
     * @param class-string<TEntity>                              $entityClass
     * @param ResourceReadability<TCondition, TSorting, TEntity> $readability
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $typeName,
        string $entityClass,
        ResourceReadability $readability,
        MessageFormatter $messageFormatter,
        ?LoggerInterface $logger,
    ) {
        parent::__construct($typeName, $entityClass, $readability, $messageFormatter, $logger);
    }

    /**
     * Determines which attributes to include in the API response.
     * If specific fields were requested via fieldset, uses parent behavior.
     * Otherwise, gets attributes from the CustomField instance or returns all available attributes.
     *
     * @return array<non-empty-string, AttributeReadabilityInterface<TEntity>>
     */
    protected function getEffectiveAttributeReadabilities(Scope $scope): array
    {
        $fieldsetBag = $scope->getManager()->getFieldset($this->typeName);
        if (null === $fieldsetBag) {
            return $this->getApiAttributesForField($scope);
        }

        return parent::getEffectiveAttributeReadabilities($scope);
    }

    private function getApiAttributesForField($scope): array
    {
        $customFieldInstance = $scope->getResource()->getData();

        if ($customFieldInstance instanceof CustomFieldInterface) {
            return $customFieldInstance->getApiAttributes();
        }

        // If no fieldset was requested, return ALL attribute fields
        // Get attributes from the ResourceReadability which is accessible in this class
        return $this->readability->getAttributes();
    }
}
