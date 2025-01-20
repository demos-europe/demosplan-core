<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Admin;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions as AttributeDplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\CsvHelper;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementStatistic;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use Twig\Extension\EscaperExtension;

/**
 * Stellt Adminfunktionen zur Verfügung.
 */
class DemosPlanAdminController extends BaseController
{
    private const ROLES_EXCLUDED_IN_EXPORT = [
        RoleInterface::API_AI_COMMUNICATOR,
        RoleInterface::GUEST,
        RoleInterface::PROSPECT,
        RoleInterface::CITIZEN,
    ];

    /**
     * Generiert die HTML Seite für die Statistik.
     *
     * @throws Exception
     */
    #[AttributeDplanPermissions('area_statistics')]
    #[Route(path: '/statistik', name: 'DemosPlan_statistics', defaults: ['format' => 'html', 'part' => 'all'])]
    #[Route(path: '/statistik/{part}/csv', name: 'DemosPlan_statistics_csv', defaults: ['format' => 'csv'])]
    public function generateStatisticsAction(
        CsvHelper $csvHelper,
        CustomerService $customerProvider,
        Environment $twig,
        NameGenerator $nameGenerator,
        OrgaService $orgaService,
        ProcedureService $procedureService,
        StatementService $statementService,
        UserService $userService,
        string $part,
        string $format
    ): ?Response {
        $templateVars = [];

        $procedureList = $procedureService->getProcedureFullList();

        // Verfahrensschritte
        $internalPhases = $this->globalConfig->getInternalPhasesAssoc();
        $externalPhases = $this->globalConfig->getExternalPhasesAssoc();

        // T17387:
        $originalStatements = $statementService->getOriginalStatements();
        $amountOfProcedures = $procedureService->getAmountOfProcedures();
        $globalStatementStatistic = new StatementStatistic($originalStatements, $amountOfProcedures);
        $templateVars['statementStatistic'] = $globalStatementStatistic;

        if ($procedureList['total'] > 0) {
            foreach ($procedureList['result'] as $procedureData) {
                $procedureData['phaseName'] = $this->globalConfig->getPhaseNameWithPriorityInternal($procedureData['phase']);
                $procedureData['publicParticipationPhaseName'] = $this->globalConfig->getPhaseNameWithPriorityExternal($procedureData['publicParticipationPhase']);
                $procedureData['statementStatistic'] = $globalStatementStatistic->getStatisticDataForProcedure($procedureData['id']);

                $procedureList['result'][$procedureData['id']] = $procedureData; // actually overwrite data

                // speichere die Anzahl der Phasen zwischen
                if (0 < strlen((string) $procedureData['phase'])) {
                    // Wenn der key num noch nicht vorhanden ist, lege ihn an
                    isset($internalPhases[$procedureData['phase']]['num']) ? $internalPhases[$procedureData['phase']]['num']++ : $internalPhases[$procedureData['phase']]['num'] = 1;
                }
                if (0 < strlen((string) $procedureData['publicParticipationPhase'])) {
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
        $templateVars['orgaList'] = $orgaService->getOrgaCountByTypeTranslated($customerProvider->getCurrentCustomer());
        $templateVars['orgaUsersList'] = $userService->getOrgaUsersList();
        $allowedRoleCodeMap = [];
        foreach ($this->getParameter('roles_allowed') as $allowedRoleCode) {
            if (!in_array($allowedRoleCode, self::ROLES_EXCLUDED_IN_EXPORT, true)
            ) {
                $allowedRoleCodeMap[$allowedRoleCode] = RoleInterface::ROLE_CODE_NAME_MAP[$allowedRoleCode];
            }
        }
        $templateVars['allowedRoleCodeMap'] = $allowedRoleCodeMap;

        $title = 'statistic';
        if ('html' === $format) {
            return $this->renderTemplate('@DemosPlanCore/DemosPlanAdmin/statistics.html.twig', [
                'templateVars' => $templateVars,
                'title'        => $title,
            ]);
        }

        // set csv Escaper
        $twig->getExtension(EscaperExtension::class)->setEscaper(
            'csv',
            fn ($twigEnv, $string, $charset) => str_replace('"', '""', (string) $string)
        );

        $response = $this->renderTemplate('@DemosPlanCore/DemosPlanAdmin/statistics.csv.twig', [
            'templateVars' => $templateVars,
            'title'        => $title,
            'part'         => $part,
        ]);

        return $csvHelper->prepareCsvResponse($response, $part, $nameGenerator);
    }
}
