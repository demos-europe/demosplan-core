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
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Response\BinaryFileDownload;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class AddonAssetController extends BaseController
{
    /**
     * Serve an addon's built ES module bundle by URL, so the frontend can `import()` it directly
     * instead of transporting the source over RPC and `eval`-ing it. Trust model matches core's own
     * static JS bundles under `public/`: no permission check here, access is already gated by
     * FrontendAssetProvider only ever handing out a URL for hooks the requesting user is allowed to see.
     */
    #[Route(
        path: '/addon-assets/{addonName}/{filename}',
        name: 'core_addon_asset',
        requirements: ['addonName' => '.+'],
        options: ['expose' => true]
    )]
    public function asset(FrontendAssetProvider $assetProvider, string $addonName, string $filename): Response
    {
        $filePath = $assetProvider->resolveAssetFilePath($addonName, $filename);

        if (null === $filePath) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileDownload($filePath, $filename, false);
        $response->headers->set('Content-Type', 'application/javascript');
        $response->setPublic();

        return $response;
    }
}
