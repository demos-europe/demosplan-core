<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GenericApiController extends APIController
{
    /**
     * @Route(path="/api/2.0/{resourceType}",
     *        methods={"GET"},
     *        name="api_resource_list",
     *        options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_json_api_list")
     */
    public function listAction(
        JsonApiActionService $resourceService,
        string $resourceType
    ): APIResponse {
        $collection = $resourceService->listFromRequest($resourceType, $this->request->query);

        return $this->renderResource($collection);
    }

    /**
     * @Route(path="/api/2.0/{resourceType}/{resourceId}",
     *        methods={"PATCH"},
     *        name="api_resource_update",
     *        options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_json_api_update")
     */
    public function updateAction(
        JsonApiActionService $resourceService,
        string $resourceType,
        string $resourceId
    ): Response {
        $requestJson = $this->getRequestJson();
        $item = $resourceService->updateFromRequest($resourceType, $resourceId, $requestJson, $this->request->query);

        if (null !== $item) {
            return $this->renderResource($item);
        }

        return $this->createEmptyResponse();
    }

    /**
     * @Route(path="/api/2.0/{resourceType}",
     *        methods={"POST"},
     *        name="api_resource_create",
     *        options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_json_api_create")
     */
    public function createAction(string $resourceType, JsonApiActionService $resourceService): Response
    {
        $requestJson = $this->getRequestJson();
        $item = $resourceService->createFromRequest($resourceType, $requestJson, $this->request->query);

        if (null === $item) {
            return $this->renderEmpty(Response::HTTP_NO_CONTENT);
        }

        return $this->renderResource($item, Response::HTTP_CREATED);
    }

    /**
     * @Route(path="/api/2.0/{resourceType}/{resourceId}",
     *        methods={"DELETE"},
     *        name="api_resource_delete",
     *        options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_json_api_delete")
     *
     * @return APIResponse
     */
    public function deleteAction(
        JsonApiActionService $resourceService,
        string $resourceType,
        string $resourceId
    ): Response {
        $resourceService->deleteFromRequest($resourceType, $resourceId);

        return $this->createEmptyResponse();
    }

    /**
     * @Route(
     *     "/api/2.0/{resourceType}/{resourceId}",
     *     name="api_resource_get",
     *     options={"expose": true},
     *     methods={"GET"}
     * )
     *
     * @DplanPermissions("feature_json_api_get")
     */
    public function getAction(
        JsonApiActionService $resourceService,
        string $resourceType,
        string $resourceId
    ): Response {
        $item = $resourceService->getFromRequest($resourceType, $resourceId, $this->request->query);

        return $this->renderResource($item);
    }
}
