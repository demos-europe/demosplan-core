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
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
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
     * @DplanPermissions("area_statistics")
     *
     * @param string $part
     * @param string $format
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statistics', path: '/statistik', defaults: ['format' => 'html', 'part' => 'all'])]
    #[Route(name: 'DemosPlan_statistics_csv', path: '/statistik/{part}/csv', defaults: ['format' => 'csv'])]
    public function generateStatisticsAction(
        Environment $twig,
        OrgaService $orgaService,
        CustomerService $customerProvider,
        NameGenerator $nameGenerator,
        ProcedureService $procedureService,
        StatementService $statementService,
        UserService $userService,
        $part,
        $format
    ): ?Response {
        $templateVars = [];

/*        $data = [
            'CUstomerId' => [
                'name' => Custonername
                'Orgas' => [
                    OrgaId => [
                        'OrgaName' => name
                        'proceduresCreated' => count
                    ]
        ]
            ]
        ]:*/

        // procedureList does not contain blueprints
        $procedureList = $procedureService->getProcedureFullList();

        // T36299 track procedures created by organisation within any given customer
        $allCustomers = $customerProvider->getAllCustomers();
        $allCustomers = array_combine(
            array_map(static fn (Customer $customer): string => $customer->getId(), $allCustomers),
            array_map(
                static fn (Customer $customer): array => ['customerName' => $customer->getName(), 'orgas' => []],
                $allCustomers
            )
        );

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
                $customerId = $procedureData['customer'];
                /** @var Orga $orga */
                $orga = $procedureData['orga'];
                if (null !== $customerId) {
                    if (array_key_exists($orga->getId(), $allCustomers[$customerId]['orgas'])) {
                        $allCustomers[$customerId]['orgas'][$orga->getId()]['proceduresCreated']++;
                    } else {
                        $allCustomers[$customerId]['orgas'][$orga->getId()] = [
                            'orgaName' => $orga->getName(),
                            'proceduresCreated' => 1,
                        ];
                    }
                    // restore customerToLegacy behaviour
                    $procedureData['customer'] = null;
                }

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
        $templateVars['orgaInCustomerProcedureCreatedCount'] = $allCustomers;
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
        // T25516 UTF-8-MB for MS-excel umlauts support
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);
        $response->setContent($bom.$response->getContent());
        $filename = 'export_'.$part.'_'.date('Y_m_d_His').'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $nameGenerator->generateDownloadFilename($filename));
        $response->setCharset('UTF-8');

        return $response;
    }
}
