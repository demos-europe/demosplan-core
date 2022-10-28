<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement\ManualSimplifiedStatementCreator;
use demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement\StatementFromEmailCreator;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;

/**
 * Takes care of actions related to the simplified version of a Statement.
 */
class DemosPlanSimplifiedStatementController extends BaseController
{
    /**
     * Creates a new Statement from the simplified form.
     *
     * @Route(
     *     name="dplan_simplified_new_statement_create",
     *     methods={"POST"},
     *     path="/verfahren/{procedureId}/stellungnahmen/neu",
     *     options={"expose": true}
     * )
     *
     * @throws MessageBagException
     * @throws UserNotFoundException
     * @DplanPermissions("feature_simplified_new_statement_create")
     */
    public function createAction(
        ManualSimplifiedStatementCreator $statementCreator,
        StatementFromEmailCreator $emailStatementCreator,
        Request $request,
        string $procedureId
    ): Response {
        if ($emailStatementCreator->isImportingStatementViaEmail($request)) {
            return $emailStatementCreator($request, $procedureId);
        }

        return $statementCreator($request, $procedureId);
    }
}
