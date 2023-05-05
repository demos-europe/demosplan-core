<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiResourceServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\TransformerLoader;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\AccessException;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\JsonApiSerializer;
use LogicException;

class ApiResourceService implements ApiResourceServiceInterface
{
    /**
     * This is the limit for entity recursion in fractal, which means
     * the number of nested includes + defaultIncludes.
     */
    private const FRACTAL_RECURSION_LIMIT = 20;

    /**
     * @var Manager
     */
    private $fractal;
    /**
     * @var TransformerLoader
     */
    private $transformerLoader;

    /**
     * @var PrefilledResourceTypeProvider
     */
    private $resourceTypeProvider;

    public function __construct(TransformerLoader $transformerLoader, PrefilledResourceTypeProvider $resourceTypeProvider)
    {
        $this->fractal = new Manager();

        $jsonApiSerializer = new JsonApiSerializer();

        $this->fractal->setSerializer($jsonApiSerializer);
        $this->fractal->setRecursionLimit(self::FRACTAL_RECURSION_LIMIT);

        $this->transformerLoader = $transformerLoader;
        $this->resourceTypeProvider = $resourceTypeProvider;
    }

    public function getFractal(): Manager
    {
        return $this->fractal;
    }

    /**
     * @param string $transformerName #Service Service name or fq class name of the transformer
     *
     * @throws LogicException
     */
    public function getTransformer(string $transformerName): BaseTransformer
    {
        /** @var BaseTransformer $transformer */
        $transformer = $this->transformerLoader->get($transformerName);

        if (!is_a($transformer, BaseTransformer::class)) {
            throw new LogicException('Got '.get_class($transformer).' expected demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Transformer\BaseTransformer;');
        }

        return $transformer;
    }

    /**
     * @param iterable|CoreEntity[]|ValueObject[] $data
     * @param string                              $transformerName #Service Service name or fq class name of the transformer
     * @param string                              $type            optionally override the item type
     */
    public function makeCollection($data, string $transformerName, $type = ''): Collection
    {
        /** @var BaseTransformer $transformer */
        $transformer = $this->getTransformer($transformerName);

        if ('' === $type) {
            $type = $transformer->getType();
        }

        return new Collection($data, $transformer, $type);
    }

    /**
     * @param                 $data
     * @param BaseTransformer $baseTransformer
     * @param                 $type
     *
     * @return Collection
     */
    public function makeAddonCollection($data, BaseTransformer $baseTransformer, $type = ''): Collection
    {
        $transformerName = get_class($baseTransformer);
        return $this->makeCollection($data, $transformerName, $type);
    }

    /**
     * @param iterable|CoreEntity[]|ValueObject[] $data
     * @param string                              $resourceTypeName The value returned by {@link ResourceTypeInterface::getName()}
     */
    public function makeCollectionOfResources($data, string $resourceTypeName): Collection
    {
        $resourceType = $this->resourceTypeProvider->requestType($resourceTypeName)
            ->instanceOf(ResourceTypeInterface::class)
            ->getInstanceOrThrow();

        if (!$resourceType->isAvailable()) {
            throw AccessException::typeNotAvailable($resourceType);
        }

        return new Collection($data, $resourceType->getTransformer(), $resourceType::getName());
    }

    /**
     * @param array|CoreEntity|ValueObject|User $data
     * @param string                            $transformerName #Service Service name or fq class name of the transformer
     * @param string                            $type            optionally override the item type
     */
    public function makeItem($data, string $transformerName, $type = ''): Item
    {
        /** @var BaseTransformer $transformer */
        $transformer = $this->getTransformer($transformerName);

        if ('' === $type) {
            $type = $transformer->getType();
        }

        return new Item($data, $transformer, $type);
    }

    /**
     * @param string $resourceTypeName The value returned by {@link ResourceTypeInterface::getName()}
     */
    public function makeItemOfResource($data, string $resourceTypeName): Item
    {
        $resourceType = $this->resourceTypeProvider->requestType($resourceTypeName)
            ->instanceOf(ResourceTypeInterface::class)
            ->getInstanceOrThrow();

        if (!$resourceType->isAvailable()) {
            throw AccessException::typeNotAvailable($resourceType);
        }

        return new Item($data, $resourceType->getTransformer(), $resourceType::getName());
    }
}
