<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Breadcrumb;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Twig\Extension\ExtensionBase;
use Twig\TwigFunction;

/**
 * Generiere die Breadcrumb aus einem pagetitle key.
 */
class BreadcrumbTwigExtension extends ExtensionBase
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('demosBreadcrumbRender', $this->renderBreadcrumb(...)),
        ];
    }

    /**
     * Generiere die Breadcrumb aus einem translations key.
     *
     * @param User       $user         - current user
     * @param string     $pageTitleKey
     * @param array|null $procedure    - procedure as array
     * @param bool       $isOwner      determines if the organisation of the current user owner or planner of the procedure
     */
    public function renderBreadcrumb($user, $pageTitleKey, $procedure = null, $isOwner = false): string
    {
        // this is not the huge symfony container but a special small one
        // to avoid loading dependencies on every twig call
        // https://symfonycasts.com/screencast/symfony-doctrine/service-subscriber
        /** @var Breadcrumb $breadcrumb */
        $breadcrumb = $this->container->get(Breadcrumb::class);
        $breadcrumb->setTitle($pageTitleKey);

        return $breadcrumb->getMarkup($user, $pageTitleKey, $procedure, $isOwner);
    }

    public static function getSubscribedServices(): array
    {
        return [
            Breadcrumb::class,
        ];
    }
}
