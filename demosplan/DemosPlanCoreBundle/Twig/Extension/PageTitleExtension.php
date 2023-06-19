<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TwigFunction;

/**
 * Generiere den Title aus einem translations key.
 */
class PageTitleExtension extends ExtensionBase
{
    /**
     * @var GlobalConfig
     */
    protected $globalConfig;

    public function __construct(ContainerInterface $container, GlobalConfigInterface $globalConfig, private readonly TranslatorInterface $translator, private readonly CurrentProcedureService $currentProcedureService, private readonly ProcedureExtension $procedureExtension)
    {
        parent::__construct($container);

        $this->globalConfig = $globalConfig;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pageTitle', $this->pageTitle(...)),
            new TwigFunction('breadcrumbTitle', $this->breadcrumbTitle(...)),
        ];
    }

    /**
     * Create Browser Title from translation key.
     */
    public function pageTitle(string $pageTitleKey): string
    {
        $parts = [];

        if ('' !== $pageTitleKey) {
            $parts[] = $this->getTranslatedPageTitle($pageTitleKey);
        }

        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null !== $currentProcedure) {
            $parts[] = $this->procedureExtension->getNameFunction($currentProcedure);
        }

        $projectPageTitle = $this->globalConfig->getProjectPagetitle();
        // avoid duplicate output
        $key = count($parts) - 1;
        if (!array_key_exists($key, $parts) || $parts[$key] !== $projectPageTitle) {
            $parts[] = $projectPageTitle;
        }

        return implode(' | ', $parts);
    }

    public function breadcrumbTitle(string $pageTitleKey): string
    {
        return $this->getTranslatedPageTitle($pageTitleKey);
    }

    private function getTranslatedPageTitle(string $pageTitleKey): string
    {
        return $this->translator->trans($pageTitleKey, ['projectName' => $this->globalConfig->getProjectName()], 'page-title');
    }
}
