<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanStatementBundle\Logic\SimplifiedStatement\PdfToStatementCreator;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Takes care of actions related to importing a PDF into a Statement.
 */
class DemosPlanPdfToStatementController extends BaseController
{
    /**
     * Imports a PDF into a Statement.
     *
     * @Route(
     *     name="dplan_pdf_import_to_statement",
     *     methods={"POST"},
     *     path="/verfahren/{procedureId}/pdf-to-stellungnahme",
     *     options={"expose": true}
     * )
     *
     * @throws MessageBagException
     * @throws UserNotFoundException
     *
     * @DplanPermissions("feature_import_statement_pdf")
     */
    public function importPdfToStatementAction(
        Request $request,
        PdfToStatementCreator $statementCreator,
        string $procedureId
    ): Response {
        return $statementCreator($request, $procedureId);
    }
}
