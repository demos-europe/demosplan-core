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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;
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
        path: '/addon-assets/{addonVendor}/{addonName}/{hookName}/{filename}',
        name: 'core_addon_asset',
        options: ['expose' => true]
    )]
    public function asset(
        FrontendAssetProvider $assetProvider,
        string $addonVendor,
        string $addonName,
        string $hookName,
        string $filename,
    ): Response {
        $fullAddonName = $addonVendor.'/'.$addonName;

        $filePath = $assetProvider->resolveAssetFilePath($fullAddonName, $hookName, $filename);

        if (null === $filePath) {
            throw new NotFoundHttpException();
        }

        // automatic mime type discovery in the BinaryFileDownload hiding in $this->file() consistently fails
        $mimeType = match (true) {
            str_ends_with($filePath, '.js')  => 'text/javascript; charset=UTF-8',
            str_ends_with($filePath, '.map') => 'application/json',
            str_ends_with($filePath, '.css') => 'text/css',

            default => MimeTypes::getDefault()->guessMimeType($filePath) ?? 'application/octet-stream',
        };

        $response = $this->file(
            $filePath,
            $filename,
            ResponseHeaderBag::DISPOSITION_INLINE,
        );

        // override the mime type guessed by BinaryFileResponse
        $response->headers->set('Content-Type', $mimeType);

        // disable caching to avoid stale addon code being loaded
        // (we don't want to rely on the browser cache for addon code, since it can change frequently)
        // NOTE: in the future, caching here can be implemented with more config updates on demosplan-addon-client-builder
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
