<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ApiResources\Transformers;

use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use League\Fractal\TransformerAbstract;
use LogicException;

/**
 * Configuration object for API Platform relationships in EDT context.
 *
 * Used to pass API Platform relationship info to EDT builders without
 * needing an EDT ResourceType.
 */
class ApiPlatformRelationshipConfig extends DplanResourceType
{
    /**
     * @param string          $typeName    JSON:API type name (e.g., 'Claim')
     * @param class-string<T> $entityClass Entity class (e.g., User::class)
     */
    public function __construct(
        private readonly string $typeName,
        private readonly string $entityClass,
        private ProviderInterface $stateProvider,
        private string $resourceClass,
    ) {
    }

    /**
     * Type name for JSON:API responses.
     */
    public static function getName(): string
    {
        // This can't be static with dynamic values, but ExtendedDynamicTransformer
        // will use getTypeName() instance method instead
        throw new LogicException('Use getTypeName() instance method instead of static getName()');
    }

    /**
     * Get type name (instance method, preferred over static getName).
     */
    public function getTypeName(): string
    {
        return $this->typeName;
    }

    /**
     * Entity class this type represents.
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * No properties - API Platform resource defines them.
     */
    protected function getProperties(): array
    {
        return [];
    }

    /**
     * Available for relationship use.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Direct GET not allowed (use API Platform endpoint).
     */
    public function isGetAllowed(): bool
    {
        return false;
    }

    /**
     * Direct LIST not allowed (use API Platform endpoint).
     */
    public function isListAllowed(): bool
    {
        return false;
    }

    /**
     * No access conditions (not used for direct access).
     */
    protected function getAccessConditions(): array
    {
        return [];
    }

    /**
     * ⚠️ THIS METHOD SHOULD NEVER BE CALLED ⚠️.
     *
     * ExtendedDynamicTransformer intercepts by checking getTypeName()
     * and returns API Platform transformer instead.
     */
    public function getTransformer(): TransformerAbstract
    {
        throw new LogicException('ApiPlatformRelationshipConfig.getTransformer() should never be called. ExtendedDynamicTransformer should intercept based on type name. Check: (1) StatementResourceType.getTransformer() returns ExtendedDynamicTransformer, (2) ExtendedDynamicTransformer.setApiPlatformDependencies() was called.');
    }

    public function getStateProvider(): ProviderInterface
    {
        return $this->stateProvider;
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }
}
