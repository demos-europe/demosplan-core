<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Document;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\HiddenElementUpdateException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanElementsAPIController extends APIController
{
    /**
     * @Route(path="/api/1.0/documents/{procedureId}/elements/{elementsId}",
     *        methods={"PATCH"},
     *        name="dp_api_documents_elements_update",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_admin")
     */
    public function updateElementsAction(ElementsService $elementsService, PermissionsInterface $permissions, $procedureId, string $elementsId): Response
    {
        $elementsToUpdate = $elementsService->getElementObject($elementsId);

        if (null === $elementsToUpdate) {
            return $this->renderError(Response::HTTP_NOT_FOUND);
        }

        if ('map' !== $elementsToUpdate->getCategory()
            && !$permissions->hasPermission('feature_map_deactivate')
        ) {
            return $this->renderError(Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $elementsToUpdate->setEnabled($this->requestData['Elements'][$elementsId]['enabled']);

        try {
            $elementsService->updateElementObject($elementsToUpdate);
        } catch (HiddenElementUpdateException $e) {
            // FE tried to update hidden element, no special handling yet
        }
        $this->messageBag->add('confirm', 'confirm.all.changes.saved');

        return $this->renderSuccess();
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @Route(path="/api/1.0/element/{elementId}",
     *        methods={"GET"},
     *        name="dp_api_elements_get",
     *        options={"expose": true})
     *
     * @return APIResponse|JsonResponse
     */
    public function getAction(
        ApiResourceService $apiResourceService,
        ElementHandler $elementHandler,
        string $elementId
    ): JsonResponse {
        try {
            $element = $elementHandler->getElement($elementId);
            $resource = $apiResourceService->makeItemOfResource(
                $element,
                PlanningDocumentCategoryResourceType::getName()
            );

            return $this->renderResource($resource);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
