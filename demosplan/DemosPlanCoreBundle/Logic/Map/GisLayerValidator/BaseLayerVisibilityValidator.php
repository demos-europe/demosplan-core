<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map\GisLayerValidator;


use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\LegacyFlashMessageCreator;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapHandler;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Services\Map\GetFeatureInfo;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


class BaseLayerVisibilityValidator {

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MapService $mapService,
        private readonly MapHandler $mapHandler,
    ) {
    }


    /**
     * Disable default visibility for all base layers except the given one.
     *
     * @param string      $procedureId   The procedure ID
     * @param string|null $exceptLayerId The layer ID to exclude from disabling
     */
    public function disableOtherBaseLayersDefaultVisibility(string $procedureId, ?string $exceptLayerId): void
    {
        try {
            // Get all layers for this procedure
            $allLayers = $this->mapService->getGisAdminList($procedureId);
            $layerObjects = $this->mapService->getLayerObjects($allLayers);

            foreach ($layerObjects as $layer) {
                // Skip if not a base layer, or if it's the layer we're currently saving
                if (!$layer->isBaseLayer() || $layer->getId() === $exceptLayerId) {
                    continue;
                }

                // Skip if already disabled
                if (!$layer->hasDefaultVisibility()) {
                    continue;
                }

                // Disable default visibility for this base layer
                $this->mapHandler->updateGis([
                    'id'                => $layer->getId(),
                    'defaultVisibility' => false,
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to disable other base layers default visibility', [
                'exception'     => $e,
                'procedureId'   => $procedureId,
                'exceptLayerId' => $exceptLayerId,
            ]);
        }
    }

}
