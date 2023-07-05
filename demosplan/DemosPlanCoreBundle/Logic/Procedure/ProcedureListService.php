<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\LocationHandler;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;

class ProcedureListService extends CoreService
{
    public function __construct(private readonly Breadcrumb $breadcrumb, private readonly CurrentUserInterface $currentUser, private readonly FileService $fileService, private readonly LocationHandler $locationHandler)
    {
    }

    /**
     * Prepares info used by frontend when filtering by ars.
     */
    public function getSearchByArsResultsTitle(
        string $ars,
        array $templateVars): string
    {
        $nResults = isset($templateVars['list']['procedurelist'])
            ? is_countable($templateVars['list']['procedurelist']) ? count($templateVars['list']['procedurelist']) : 0
            : 0;

        $location = $this->locationHandler->findByArs($ars);

        return $this->locationHandler->getFilterResultMessage($location, $nResults);
    }

    /**
     * Prepares info used by frontend when filtering by gkz.
     */
    public function getSearchByGkzResultsTitle(
        string $gkz,
        array $templateVars): string
    {
        $nResults = isset($templateVars['list']['procedurelist'])
            ? is_countable($templateVars['list']['procedurelist']) ? count($templateVars['list']['procedurelist']) : 0
            : 0;

        $location = $this->locationHandler->findByMunicipalCode($gkz);

        return $this->locationHandler->getFilterResultMessage($location, $nResults);
    }

    public function generateProcedureBaseTemplateVars(array $templateVars, string $title): array
    {
        // Füge die kontextuelle Hilfe dazu
        $templateVars['contextualHelpBreadcrumb'] = $this->breadcrumb->getContextualHelp($title);
        $templateVars['freeDiskSpaceAsString'] = '';
        $templateVars['freeDiskSpaceInBytes'] = 0;

        if ($this->currentUser->hasPermission('feature_show_free_disk_space')) {
            $templateVars['freeDiskSpaceAsString'] = $this->fileService->getFreeDiskSpaceAsText();
            $templateVars['freeDiskSpaceInBytes'] = $this->fileService->getRemainingDiskSpace();
        }

        return $templateVars;
    }
}
