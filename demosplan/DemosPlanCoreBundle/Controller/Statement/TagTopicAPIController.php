<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Symfony\Component\Routing\Annotation\Route;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\APIController;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceObject;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\TopLevel;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagTopicResourceType;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanStatementBundle\Exception\DuplicatedTagTopicTitleException;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;

class TagTopicAPIController extends APIController
{
    /**
     * @Route(path="/api/1.0/TagTopic/",
     *        methods={"POST"},
     *        name="dplan_api_tag_topic_create",
     *        options={"expose": true})
     *
     * @DplanPermissions("feature_json_api_tag_topic_create")
     */
    public function createAction(
        CurrentProcedureService $currentProcedureService,
        PermissionsInterface $permissions,
        StatementHandler $statementHandler,
        TagTopicResourceType $tagTopicResourceType
    ): APIResponse {
        if (!($this->requestData instanceof TopLevel)) {
            throw BadRequestException::normalizerFailed();
        }
        /** @var ResourceObject $tagResourceObject */
        $tagResourceObject = $this->requestData->getFirst('TagTopic');
        if (!$tagResourceObject instanceof ResourceObject) {
            throw new BadRequestException('Insufficient data in JSON request.');
        }

        $title = $tagResourceObject->get('attributes.title');
        $procedureId = $tagResourceObject->get('relationships.procedure.data.id');

        if ($procedureId === $currentProcedureService->getProcedureIdWithCertainty()) {
            throw new BadRequestException('Contradicting request');
        }

        if (!$permissions->ownsProcedure()) {
            throw new BadRequestException('Access denied');
        }

        try {
            $tag = $statementHandler->createTopic($title, $procedureId);
            $this->getMessageBag()->add('confirm', 'confirm.topic.created');

            return $this->renderItemOfResource($tag, $tagTopicResourceType);
        } catch (DuplicatedTagTopicTitleException $e) {
            $this->messageBag->add('error', 'topic.create.duplicated.title');

            throw $e;
        }
    }
}
