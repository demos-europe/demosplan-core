<?php

declare(strict_types=1);

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
 * Provides admin functions.
 */
class DemosPlanAdminController extends BaseController
{
    private const STATISTICS_TITLE = 'statistic';
    public function __construct(
        private readonly Environment $twig,
        private readonly CsvHelper $csvHelper,
        private readonly NameGenerator $nameGenerator,
        private readonly StatisticsGenerator $statisticsGenerator,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AttributeDplanPermissions('area_statistics')]
    #[Route(path: '/statistik', name: 'DemosPlan_statistics', defaults: ['format' => 'html', 'part' => 'all'])]
    #[Route(path: '/statistik/{part}/csv', name: 'DemosPlan_statistics_csv', defaults: ['format' => 'csv'])]
    public function generateStatisticsAction(
        string $part,
        string $format
    ): ?Response {
        $statistics = $this->statisticsGenerator->generateStatistics($this->getParameter('roles_allowed'));

        return $this->renderStatisticsTemplate(
            $statistics->getAsTemplateVars(),
            $format,
            $part
        );
    }

    /**
     * @throws Exception
     */
    private function renderStatisticsTemplate(array $templateVars, string $format, string $part): ?Response
    {
        if ('html' === $format) {
            return $this->renderTemplate('@DemosPlanCore/DemosPlanAdmin/statistics.html.twig', [
                'templateVars' => $templateVars,
                'title'        => self::STATISTICS_TITLE,
            ]);
        }

        // set csv Escaper
        $this->twig->getExtension(EscaperExtension::class)->setEscaper(
            'csv',
            fn ($twigEnv, $string, $charset) => str_replace('"', '""', (string) $string)
        );

        $response = $this->renderTemplate('@DemosPlanCore/DemosPlanAdmin/statistics.csv.twig', [
            'templateVars' => $templateVars,
            'title'        => self::STATISTICS_TITLE,
            'part'         => $part,
        ]);

        return $this->csvHelper->prepareCsvResponse($response, $part, $this->nameGenerator);
    }
}
