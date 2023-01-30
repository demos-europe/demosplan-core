<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanMapBundle\Logic\MapService;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WerDenktWasAPIController extends BaseController
{
    /**
     * @Route(
     *      path="/api/werdenktwas/procedures",
     *     methods={"GET"}
     * )
     * @DplanPermissions("area_public_participation")
     */
    public function procedureListGeoJSONAction(TranslatorInterface $translator): ?JsonResponse
    {
        $searchProceduresResponse = $this->forward(
            '\demosplan\DemosPlanCoreBundle\Controller\Procedure\DemosPlanProcedureAPIController::searchProceduresAjaxAction',
        );

        if (!is_a($searchProceduresResponse, JsonResponse::class)) {
            return new JsonResponse(null);
        }

        $data = json_decode($searchProceduresResponse->getContent(), true);

        $geojson = [
            'type'       => 'FeatureCollection',
            'features'   => [],
        ];

        $projection = new Proj4php();
        $sourceProjection = new Proj(MapService::PSEUDO_MERCATOR_PROJECTION_LABEL, $projection);
        $targetProjection = new Proj(MapService::EPSG_4326_PROJECTION_LABEL, $projection);

        foreach ($data['data'] as $procedureInfo) {
            $feature = [
                'type' => 'Feature',
            ];

            $coordinates = array_map(static function ($coordinate) {
                return (float) $coordinate;
            }, explode(',', $procedureInfo['attributes']['coordinate']));

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
        }

        $response = new JsonResponse($geojson);
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
