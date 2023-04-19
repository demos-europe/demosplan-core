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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatementAnonymizeController extends BaseController
{
    /**
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws Exception
     *
     * @Route(
     *     path="/procedure/{procedureId}/statement/{statementId}/anonymize",
     *     name="DemosPlan_statement_anonymize_view",
     *     options={"expose": true}
     * )
     *
     * @DplanPermissions("area_statement_anonymize")
     */
    public function statementAnonymizeAction(
        AssessmentHandler $assessmentHandler,
        StatementHandler $statementHandler,
        string $procedureId,
        string $statementId
    ): Response {
        $statement = $statementHandler->getStatement($statementId);

        // redirect to original statements if statement id not correct
        if (!$statement instanceof Statement) {
            $this->getMessageBag()->add('error', 'error.statement.not.found');

            return $this->redirectToRoute(
                'dplan_assessmenttable_view_original_table',
                ['procedureId' => $procedureId]
            );
        }

        // redirect to original statement if original statement is not selected
        if (!$statement->isOriginal()) {
            return $this->redirectToRoute(
                'DemosPlan_statement_anonymize_view',
                [
                    'procedureId' => $procedureId,
                    'statementId' => $statement->getOriginalId(),
                ]
            );
        }

        $templateVars = [];
        $templateVars['statement'] = $statement;
        $templateVars['linkToStatementChildren'] = $assessmentHandler->generateAssessmentTableFilterLinkFromExternId(
            $statement
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/statement_anonymize.html.twig',
            [
                'procedureId'  => $procedureId,
                'templateVars' => $templateVars,
                'title'        => 'statement.anonymize',
            ]
        );
    }
}
