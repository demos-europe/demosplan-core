<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\SearchCapableListRequest;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\DeletableResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\GetableResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\ListableResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableResourceTypeInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use EDT\JsonApi\RequestHandling\RequestConstraintFactory;
use EDT\JsonApi\Requests\CreationRequest;
use EDT\JsonApi\Requests\DeletionRequest;
use EDT\JsonApi\Requests\GetRequest;
use EDT\JsonApi\Requests\RequestException;
use EDT\JsonApi\Requests\UpdateRequest;
use EDT\Wrapping\Contracts\TypeRetrievalAccessException;
use League\Fractal\Resource\Item;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

/**
 * Entry point for JSON:API requests.
 *
 * Currently, this controller handles URL paths starting with `/api/2.0/` only. Legacy and
 * thus potentially specification-violating `/api/1.0/` paths are handled in separate controllers.
 *
 * The goal is to unify all JSON:API requests in this controller and consequently dropping
 * the `1.0`/`2.0` from the URL paths.
 *
 * This controller has access to only and all resource type instances that are service-tagged
 * with `dplan.resourceType`, no matter if they were added via addon or reside in the core.
 */
class GenericApiController extends APIController
{
    /**
     * Fetches resources of the given type.
     *
     * The instance corresponding to the given resource type name must implement
     * {@link ListableResourceTypeInterface}.
     *
     * @see https://jsonapi.org/format/1.1/#fetching-resources Fetching Resources
     *
     * @throws TypeRetrievalAccessException
     * @throws RequestException
     */
    #[DplanPermissions('feature_json_api_list')]
    #[Route(
        path: '/api/2.0/{resourceType}',
        name: 'api_resource_list',
        options: ['expose' => true],
        methods: ['GET']
    )]
    public function list(
        SearchCapableListRequest $listRequest,
        string $resourceType,
    ): APIResponse {
        // fetch resource type instance
        $type = $this->resourceTypeProvider->getTypeByIdentifier($resourceType);
        Assert::isInstanceOf($type, JsonApiResourceTypeInterface::class);

        // check implementation
        if (!$type instanceof ListableResourceTypeInterface) {
            throw new BadRequestException("The resource type `$resourceType` is not configured for JSON:API `list` requests.");
        }

        // check permissions
        if (!$type->isListAllowed()) {
            throw new BadRequestException("The resource type `$resourceType` is not allowed for JSON:API `list` requests.");
        }

        // execute listing
        $collection = $listRequest->searchResources($type);

        // create response
        return $this->renderResource($collection);
    }

    /**
     * Updates a single resource of the given type.
     *
     * The instance corresponding to the given resource type name must implement
     * {@link UpdatableResourceTypeInterface}.
     *
     * @see https://jsonapi.org/format/1.1/#crud-updating Updating Resources
     *
     * @throws TypeRetrievalAccessException
     * @throws RequestException
     */
    #[DplanPermissions('feature_json_api_update')]
    #[Route(
        path: '/api/2.0/{resourceType}/{resourceId}',
        name: 'api_resource_update',
        options: ['expose' => true],
        methods: ['PATCH']
    )]
    public function update(
        EventDispatcherInterface $eventDispatcher,
        Request $request,
        ValidatorInterface $validator,
        RequestConstraintFactory $requestConstraintFactory,
        string $resourceType,
        string $resourceId,
    ): Response {
        // Dependency Injection of UpdateRequest does not work in tests,
        // content of the Request is not passed to the UpdateRequest
        $updateRequest = new UpdateRequest(
            $eventDispatcher,
            $request,
            $validator,
            $requestConstraintFactory,
            512
        );

        // fetch resource type instance
        $type = $this->resourceTypeProvider->getTypeByIdentifier($resourceType);

        // check implementation
        if (!$type instanceof UpdatableResourceTypeInterface) {
            throw new BadRequestException("The resource type `$resourceType` is not configured for JSON:API `update` requests.");
        }

        // check permissions
        if (!$type->isUpdateAllowed()) {
            throw new BadRequestException("The resource type `$resourceType` is not allowed for JSON:API `update` requests.");
        }

        // execute update
        $item = $updateRequest->updateResource($type, $resourceId);

        // create response
        return $item instanceof Item
            ? $this->renderResource($item)
            : $this->createEmptyResponse();
    }

    /**
     * Creates a single resource of the given type.
     *
     * The instance corresponding to the given resource type name must implement
     * {@link CreatableResourceTypeInterface}.
     *
     * @see https://jsonapi.org/format/1.1/#crud-creating Creating Resources
     *
     * @throws TypeRetrievalAccessException
     * @throws RequestException
     */
    #[DplanPermissions('feature_json_api_create')]
    #[Route(
        path: '/api/2.0/{resourceType}',
        name: 'api_resource_create',
        options: ['expose' => true],
        methods: ['POST']
    )]
    public function create(
        CreationRequest $creationRequest,
        string $resourceType,
    ): Response {
        // fetch resource type instance
        $type = $this->resourceTypeProvider->getTypeByIdentifier($resourceType);

        // check implementation
        if (!$type instanceof CreatableResourceTypeInterface) {
            throw new BadRequestException("The resource type `$resourceType` is not configured for JSON:API `create` requests.");
        }

        // check permissions
        if (!$type->isCreateAllowed()) {
            throw new BadRequestException("The resource type `$resourceType` is not allowed for JSON:API `create` requests.");
        }

        // execute creation
        $item = $creationRequest->createResource($type);

        // create response
        return $item instanceof Item
            ? $this->renderResource($item, Response::HTTP_CREATED)
            : $this->createEmptyResponse();
    }

    /**
     * Deletes a single resource of the given type.
     *
     * The instance corresponding to the given resource type name must implement
     * {@link DeletableResourceTypeInterface}.
     *
     * @see https://jsonapi.org/format/1.1/#crud-deleting Deleting Resources
     *
     * @throws TypeRetrievalAccessException
     * @throws RequestException
     */
    #[DplanPermissions('feature_json_api_delete')]
    #[Route(
        path: '/api/2.0/{resourceType}/{resourceId}',
        name: 'api_resource_delete',
        options: ['expose' => true],
        methods: ['DELETE']
    )]
    public function delete(
        DeletionRequest $deletionRequest,
        string $resourceType,
        string $resourceId,
    ): Response {
        // fetch resource type instance
        $type = $this->resourceTypeProvider->getTypeByIdentifier($resourceType);

        // check implementation
        if (!$type instanceof DeletableResourceTypeInterface) {
            throw new BadRequestException("The resource type `$resourceType` is not configured for JSON:API `delete` requests.");
        }

        // check permissions
        if (!$type->isDeleteAllowed()) {
            throw new BadRequestException("The resource type `$resourceType` is not allowed for JSON:API `delete` requests.");
        }

        // execute deletion
        $deletionRequest->deleteResource($type, $resourceId);

        // create response
        return $this->createEmptyResponse();
    }

    /**
     * Fetches a single resource of the given type.
     *
     * The instance corresponding to the given resource type name must implement
     * {@link GetableResourceTypeInterface}.
     *
     * @see https://jsonapi.org/format/1.1/#fetching-resources Fetching Resources
     *
     * @throws TypeRetrievalAccessException
     * @throws RequestException
     */
    #[DplanPermissions('feature_json_api_get')]
    #[Route(
        path: '/api/2.0/{resourceType}/{resourceId}',
        name: 'api_resource_get',
        options: ['expose' => true],
        methods: ['GET']
    )]
    public function getAction(
        GetRequest $getRequest,
        string $resourceType,
        string $resourceId,
    ): Response {
        // fetch resource type instance
        $type = $this->resourceTypeProvider->getTypeByIdentifier($resourceType);

        // check implementation
        if (!$type instanceof GetableResourceTypeInterface) {
            throw new BadRequestException("The resource type `$resourceType` is not configured for JSON:API `get` requests.");
        }

        // check permissions
        if (!$type->isGetAllowed()) {
            throw new BadRequestException("The resource type `$resourceType` is not allowed for JSON:API `get` requests.");
        }

        // execute get
        $item = $getRequest->getResource($type, $resourceId);

        // create response
        return $this->renderResource($item);
    }
}
