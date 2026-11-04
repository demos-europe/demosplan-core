<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Addon;

use demosplan\DemosPlanCoreBundle\Addon\FrontendAssetProvider;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A page that consists of the components an addon provides for a hook, e.g. to maintain data of the addon.
 * Addons link to it from their menu entries, see config/menus.yml of the addon. The permissions the addon declares
 * for the hook decide who may see the page.
 */
class AddonPageController extends BaseController
{
    #[DplanPermissions('area_preferences')]
    #[Route(
        path: '/addon/page/{hookName}',
        name: 'DemosPlan_addon_page',
        requirements: ['hookName' => '[A-Za-z0-9_.\-]+'],
        methods: 'GET'
    )]
    public function page(string $hookName, FrontendAssetProvider $frontendAssetProvider): Response
    {
        if (!$frontendAssetProvider->isHookAvailable($hookName)) {
            throw new NotFoundHttpException(sprintf('No addon provides the page "%s".', $hookName));
        }

        return $this->render(
            '@DemosPlanCore/DemosPlanAddon/addon_page.html.twig',
            ['hookName' => $hookName]
        );
    }
}
