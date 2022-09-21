<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TwigFunction;

/**
 * Generiere den Title aus einem translations key.
 */
class PageTitleExtension extends ExtensionBase
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var ProcedureExtension
     */
    private $procedureExtension;

    /**
     * @var GlobalConfig
     */
    protected $globalConfig;

    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureService;

    public function __construct(ContainerInterface $container, GlobalConfigInterface $globalConfig, TranslatorInterface $translator, CurrentProcedureService $currentProcedureService, ProcedureExtension $procedureExtension)
    {
        parent::__construct($container);

        $this->globalConfig = $globalConfig;
        $this->translator = $translator;
        $this->procedureExtension = $procedureExtension;
        $this->currentProcedureService = $currentProcedureService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pageTitle', [$this, 'pageTitle']),
            new TwigFunction('breadcrumbTitle', [$this, 'breadcrumbTitle']),
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
