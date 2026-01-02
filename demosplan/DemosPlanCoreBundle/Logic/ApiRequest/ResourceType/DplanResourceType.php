<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\DoctrineResourceType;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @template T of EntityInterface
 *
 * @template-extends DoctrineResourceType<T>
 */
abstract class DplanResourceType extends DoctrineResourceType
{
    use DplanResourceTypeTrait;

    /**
     * FIXME: just for experimental purposes, improve or replace altogether.
     *
     * @template TConfig of MagicResourceConfigBuilder
     *
     * @param class-string<TConfig> $class
     *
     * @return TConfig
     */
    protected function getConfig(string $class): MagicResourceConfigBuilder
    {
        return new $class($this->getEntityClass(), $this->getPropertyBuilderFactory());
    }

    public function getTypeName(): string
    {
        return $this::getName();
    }

    protected function formatDate(?DateTime $date): ?string
    {
        return $this->dplanResourceTypeService->formatDate($date);
    }

    /**
     * @return list<ClauseFunctionInterface<bool>>
     */
    abstract protected function getAccessConditions(): array;

    /**
     * @return non-empty-string
     *
     * @deprecated use {@link getTypeName} instead
     */
    abstract public static function getName(): string;

    /**
     * Fetching resources via JSON:API `get` requests is allowed by default, if the resource type is
     * set as available and directly accessible.
     *
     * Override this method to change the default.
     */
    public function isGetAllowed(): bool
    {
        return $this->isAvailable();
    }

    /**
     * Fetching resources via JSON:API `list` requests is allowed by default, if the resource type is
     * set as available and directly accessible.
     *
     * Override this method to change the default.
     */
    public function isListAllowed(): bool
    {
        return $this->isAvailable();
    }

    /**
     * Override this method if you want to allow to create resources of this type via JSON:API requests.
     */
    public function isCreateAllowed(): bool
    {
        return false;
    }

    /**
     * Override this method if you want to allow to delete resources of this type via JSON:API requests.
     */
    public function isDeleteAllowed(): bool
    {
        return false;
    }

    /**
     * Override this method if you want to allow to update resources of this type via JSON:API requests.
     */
    public function isUpdateAllowed(): bool
    {
        return false;
    }
}
