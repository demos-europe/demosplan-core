<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFragmentResourceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Handles changes to {@link StatementFragment} resources.
 */
class StatementFragmentAPIController extends APIController
{
    /**
     * @Route("/api/1.0/statement-fragment/{statementFragmentId}/edit",
     *     methods={"PATCH"},
     *     name="dplan_api_statement_fragment_edit",
     *     options={"expose": true}
     * )
     *
     * @DplanPermissions("feature_statements_fragment_edit")
     *
     * @return JsonResponse
     */
    public function updateAction(PermissionsInterface $permissions, Request $request, StatementHandler $statementHandler, string $statementFragmentId)
    {
        if (!($this->requestData instanceof TopLevel)) {
            throw BadRequestException::normalizerFailed();
        }
        /** @var ResourceObject $resourceObject */
        $resourceObject = $this->requestData->getFirst(StatementFragmentResourceType::getName());
        // if reviewer changed emit notification
        // note that by RFC 6570 query parameter keys with hyphens (-) are not valid
        // and as of JSON:API v1.1 a non [a-z] character must be included
        $notifyReviewer = 'true' === $request->query->get('notify_reviewer');
        // enable $propagateTags by default and disable it if the user has the permissions to
        // disable propagation and did use this permission to not request the propagation
        $propagateTags = true;
        if ($permissions->hasPermission('feature_optional_tag_propagation')) {
            $propagateTags = 'true' === $request->query->get('forward_tags_statement');
        }
        $result = $statementHandler->updateStatementFragmentFromResource(
            $statementFragmentId,
            $resourceObject,
            false,
            $notifyReviewer,
            $propagateTags
        );

        if ($result instanceof StatementFragment) {
            $item = $this->resourceService->makeItemOfResource($result, StatementFragmentResourceType::getName());

            return $this->renderResource($item);
        }

        throw new InvalidDataException('Could not change StatementFragment');
    }
}
