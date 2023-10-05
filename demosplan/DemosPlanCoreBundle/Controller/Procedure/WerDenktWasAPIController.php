<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class WerDenktWasAPIController extends BaseController
{
    /**
     * @DplanPermissions("area_public_participation")
     */
    #[Route(path: '/api/werdenktwas/procedures', methods: ['GET'])]
    public function procedureListGeoJSONAction(TranslatorInterface $translator, LoggerInterface $logger): ?JsonResponse
    {
        $searchProceduresResponse = $this->forward(
            '\demosplan\DemosPlanCoreBundle\Controller\Procedure\DemosPlanProcedureAPIController::searchProceduresAjaxAction',
        );

        if (!is_a($searchProceduresResponse, JsonResponse::class)) {
            return new JsonResponse(null);
        }

        $data = json_decode($searchProceduresResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $geojson = [
            'type'       => 'FeatureCollection',
            'features'   => [],
        ];

        $projection = new Proj4php();
        $sourceProjection = new Proj(MapService::PSEUDO_MERCATOR_PROJECTION_LABEL, $projection);
        $targetProjection = new Proj(MapService::EPSG_4326_PROJECTION_LABEL, $projection);

        foreach ($data['data'] as $procedureInfo) {
            try {
                $feature = [
                    'type' => 'Feature',
                ];

                $coordinates = array_map(static fn ($coordinate) => (float) $coordinate, explode(',', (string) $procedureInfo['attributes']['coordinate']));

                // skip procedures without coordinates (should not happen on production)
                if ([0.0] === $coordinates) {
                    continue;
                }

                $sourcePoint = new Point($coordinates[0], $coordinates[1], $sourceProjection);
                $targetPoint = $projection->transform($targetProjection, $sourcePoint);
                $feature['geometry'] = [
                    'type'        => 'Point',
                    'coordinates' => [$targetPoint->toArray()[0], $targetPoint->toArray()[1]],
                ];

                $feature['properties'] = [
                    'description'   => $procedureInfo['attributes']['externalDescription'],
                    'organisation'  => $procedureInfo['attributes']['owningOrganisationName'],
                    'name'          => $procedureInfo['attributes']['externalName'] ?? '',
                    'participation' => [
                        'start' => Carbon::createFromTimeString(
                            $procedureInfo['attributes']['externalStartDate'] ?? ''
                        )->toIso8601String(),
                        'end'   => Carbon::createFromTimeString(
                            $procedureInfo['attributes']['externalEndDate'] ?? ''
                        )->toIso8601String(),
                        'phase' => $translator->trans($procedureInfo['attributes']['externalPhaseTranslationKey'] ?? ''),
                    ],
                    'url'           => $this->generateUrl(
                        'DemosPlan_procedure_public_detail',
                        ['procedure' => $procedureInfo['id']],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                ];

                $geojson['features'][] = $feature;
            } catch (Throwable $e) {
                $logger->warning('Could not add procedure to GeoJSON', [
                    'procedure' => $procedureInfo,
                    'exception' => $e,
                ]);
                continue;
            }
        }

        $response = new JsonResponse($geojson);
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
