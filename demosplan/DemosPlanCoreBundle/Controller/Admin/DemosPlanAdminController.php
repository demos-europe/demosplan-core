<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Admin;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions as AttributeDplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\CsvHelper;
use demosplan\DemosPlanCoreBundle\Logic\Platform\Statistics\StatisticsGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
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
        Environment $twig,
        NameGenerator $nameGenerator,
        StatisticsGenerator $statisticsGenerator,
        string $part,
        string $format
    ): ?Response {
        $statistics = $statisticsGenerator->generateStatistics($this->getParameter('roles_allowed'));

        $templateVars = [];
        $templateVars['procedureList'] = $statistics->getProcedures();
        $templateVars['statementStatistic'] = $statistics->getGlobalStatementStatistic();
        $templateVars['internalPhases'] = $statistics->getInternalPhases();
        $templateVars['externalPhases'] = $statistics->getExternalPhases();
        $templateVars['rolesList'] = $statistics->getRoles();
        $templateVars['orgaList'] = $statistics->getOrgas();
        $templateVars['orgaUsersList'] = $statistics->getUsersPerOrga();
        $templateVars['allowedRoleCodeMap'] = $statistics->getAllowedRoleCodeMap();

        $title = 'statistic';
        return $this->renderStatisticsTemplate(
            $templateVars,
            $title,
            $format,
            $part,
            $twig,
            $csvHelper,
            $nameGenerator
        );
    }

    /**
     * @throws Exception
     */
    private function renderStatisticsTemplate(array $templateVars, string $title, string $format, string $part, Environment $twig, CsvHelper $csvHelper, NameGenerator $nameGenerator): ?Response
    {
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
