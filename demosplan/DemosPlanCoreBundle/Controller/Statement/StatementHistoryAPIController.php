<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeDisplayHandler;
use demosplan\DemosPlanCoreBundle\Transformers\HistoryDayTransformer;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanStatementBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class StatementHistoryAPIController extends APIController
{
    /**
     * @Route("/api/1.0/StatementHistory/{statementId}",
     *     methods={"GET"},
     *     name="dplan_api_statement_history_get",
     *     options={"expose": true}
     * )
     * @DplanPermissions("feature_statement_content_changes_view")
     */
    public function getAction(
        CurrentProcedureService $currentProcedureService,
        EntityContentChangeDisplayHandler $displayHandler,
        StatementHandler $statementHandler,
        string $statementId): APIResponse
    {
        $statement = $statementHandler->getStatement($statementId);

        if (null === $statement) {
            $this->messageBag->add('error', 'error.statement.not.found');
            throw StatementNotFoundException::createFromId($statementId);
        }

        $procedureId = $currentProcedureService->getProcedureIdWithCertainty();
        if ($procedureId !== $statement->getProcedureId()) {
            // otherwise user can access to any statement history by url modification
            throw new AccessDeniedException();
        }

        $data = $displayHandler->getHistoryByEntityId(
            $statementId,
            Statement::class
        );

        return $this->renderCollection($data, HistoryDayTransformer::class);
    }
}
