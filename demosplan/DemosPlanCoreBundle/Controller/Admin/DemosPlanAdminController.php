<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Admin;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanStatementBundle\ValueObject\StatementStatistic;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * Stellt Adminfunktionen zur Verfügung.
 */
class DemosPlanAdminController extends BaseController
{
    /**
     * Generiert die HTML Seite für die Statistik.
     *
     * @Route(
     *     name="DemosPlan_statistics",
     *     path="/statistik",
     *     defaults={"format": "html", "part": "all"},
     * )
     *
     * @Route(
     *     name="DemosPlan_statistics_csv",
     *     path="/statistik/{part}/csv",
     *     defaults={"format": "csv"},
     * )
     *
     * @DplanPermissions("area_statistics")
     *
     * @param string $part
     * @param string $format
     *
     * @return Response
     *
     * @throws Exception
     */
    public function generateStatisticsAction(
        CustomerService $customerService,
        Environment $twig,
        UserService $userService,
        ProcedureService $procedureService,
        StatementService $statementService,
        $part,
        $format
    ): ?Response {
        $templateVars = [];

        $procedureList = $procedureService->getProcedureFullList();

        //Verfahrensschritte
        $internalPhases = $this->globalConfig->getInternalPhasesAssoc();
        $externalPhases = $this->globalConfig->getExternalPhasesAssoc();

        //T17387:
        $originalStatements = $statementService->getOriginalStatements();
        $amountOfProcedures = $procedureService->getAmountOfProcedures();
        $globalStatementStatistic = new StatementStatistic($originalStatements, $amountOfProcedures);
        $templateVars['statementStatistic'] = $globalStatementStatistic;

        if ($procedureList['total'] > 0) {
            foreach ($procedureList['result'] as $procedureData) {
                $procedureData['phaseName'] = $this->globalConfig->getPhaseNameWithPriorityInternal($procedureData['phase']);
                $procedureData['publicParticipationPhaseName'] = $this->globalConfig->getPhaseNameWithPriorityExternal($procedureData['publicParticipationPhase']);
                $procedureData['statementStatistic'] = $globalStatementStatistic->getStatisticDataForProcedure($procedureData['id']);

                $procedureList['result'][$procedureData['id']] = $procedureData; //actually overwrite data

                // speichere die Anzahl der Phasen zwischen
                if (0 < strlen($procedureData['phase'])) {
                    // Wenn der key num noch nicht vorhanden ist, lege ihn an
                    isset($internalPhases[$procedureData['phase']]['num']) ? $internalPhases[$procedureData['phase']]['num']++ : $internalPhases[$procedureData['phase']]['num'] = 1;
                }
                if (0 < strlen($procedureData['publicParticipationPhase'])) {
                    isset($externalPhases[$procedureData['publicParticipationPhase']]['num'])
                        ? $externalPhases[$procedureData['publicParticipationPhase']]['num']++
                        : $externalPhases[$procedureData['publicParticipationPhase']]['num'] = 1;
                }
            }
        }

        $templateVars['internalPhases'] = $internalPhases;
        $templateVars['externalPhases'] = $externalPhases;

        $templateVars['procedureList'] = $procedureList['result'];

        $undeletedUsers = $userService->getUndeletedUsers();
        $templateVars['rolesList'] = $userService->collectRoleStatistics($undeletedUsers);
        $templateVars['orgaList'] = $userService->collectOrgaStatistics($undeletedUsers);
        $templateVars['orgaUsersList'] = $userService->getOrgaUsersList();

        $title = 'statistic';
        if ('html' === $format) {
            return $this->renderTemplate('@DemosPlanAdmin/DemosPlanAdmin/statistics.html.twig', [
                'templateVars' => $templateVars,
                'title'        => $title,
            ]);
        }

        // set csv Escaper
        $twig->getExtension('Twig_Extension_Core')->setEscaper('csv', function ($twigEnv, $string, $charset) {
            return str_replace('"', '""', $string);
        });

        $response = $this->renderTemplate('@DemosPlanAdmin/DemosPlanAdmin/statistics.csv.twig', [
            'templateVars' => $templateVars,
            'title'        => $title,
            'part'         => $part,
        ]);
        $filename = 'export_'.$part.'_'.date('Y_m_d_His').'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $this->generateDownloadFilename($filename));
        $response->setCharset('UTF-8');

        return $response;
    }
}
