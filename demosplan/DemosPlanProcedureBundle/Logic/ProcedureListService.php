<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\LocationHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;

class ProcedureListService extends CoreService
{
    /**
     * @var Breadcrumb
     */
    private $breadcrumb;
    /**
     * @var LocationHandler
     */
    private $locationHandler;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var FileService
     */
    private $fileService;

    public function __construct(
        Breadcrumb $breadcrumb,
        CurrentUserInterface $currentUser,
        FileService $fileService,
        LocationHandler $locationHandler
    ) {
        $this->breadcrumb = $breadcrumb;
        $this->locationHandler = $locationHandler;
        $this->currentUser = $currentUser;
        $this->fileService = $fileService;
    }

    /**
     * Prepares info used by frontend when filtering by ars.
     */
    public function getSearchByArsResultsTitle(
        string $ars,
        array $templateVars): string
    {
        $nResults = isset($templateVars['list']['procedurelist'])
            ? count($templateVars['list']['procedurelist'])
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
            ? count($templateVars['list']['procedurelist'])
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
