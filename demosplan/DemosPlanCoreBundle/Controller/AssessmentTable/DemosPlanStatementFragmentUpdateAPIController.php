<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\AssessmentTable;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService;
use demosplan\DemosPlanCoreBundle\Response\EmptyResponse;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementFragmentUpdate;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * This controller is responsible for direct changes to {@link StatementFragmentUpdate} resources.
 *
 * While {@link StatementFragment} resources may be modified as a side effect this class is __not__
 * responsible for direct changes to these resources.
 */
class DemosPlanStatementFragmentUpdateAPIController extends APIController
{
    /**
     * Accepts a new statement-fragment-update resource.
     *
     * @DplanPermissions({"area_admin_assessmenttable", "feature_statements_fragment_edit", "feature_statement_fragment_bulk_edit"})
     *
     * Action to update multiple Fragments.
     * Will create a StatementFragmentUpdate which data is given in $request.
     *
     * @return EmptyResponse|APIResponse
     *
     * @throws Exception
     */
    #[Route(path: '/api/1.0/statement-fragment-update', methods: ['POST'], name: 'dplan_api_assessment_table_statement_fragment_update_create', options: ['expose' => true])]
    public function createAction(
        CurrentProcedureService $currentProcedureService,
        StatementFragmentService $statementFragmentService,
        ValidatorInterface $validator
    ): Response {
        if (!($this->requestData instanceof TopLevel)) {
            throw BadRequestException::normalizerFailed();
        }
        /** @var ResourceObject $statementFragmentUpdateResource */
        $statementFragmentUpdateResource = $this->requestData->getFirst('statement-fragment-update');
        if (!($statementFragmentUpdateResource instanceof ResourceObject)) {
            throw new BadRequestException('Insufficient data in JSON request.');
        }
        $procedureId = $currentProcedureService->getProcedureIdWithCertainty();

        $statementFragmentUpdate = new StatementFragmentUpdate($procedureId, $statementFragmentUpdateResource, $validator);
        $statementFragmentUpdate->lock();
        $statementFragmentService->updateStatementFragmentsFromStatementFragmentUpdate(
            $statementFragmentUpdate
        );

        /*
         * If a POST request did include a Client-Generated ID and the
         * requested resource has been created successfully, the server
         * MUST return either [...] or a 204 No Content status code
         * with no response document.
         */

        return $this->createEmptyResponse();
    }
}
