<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\LocationService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocationSearchController extends BaseController
{
    /**
     * Suggest locations.
     *
     * @DplanPermissions("area_demosplan")
     * @Route(
     *     path="/suggest/location/json",
     *     name="core_suggest_location_json",
     *     options={"expose": true}
     * )
     */
    public function searchLocationJsonAction(Request $request, LocationService $locationService): Response
    {
        try {
            $query = $request->query->all();

            // return empty suggestions if no query set
            if (!isset($query['query']) || '' === $query['query']) {
                return new JsonResponse(['suggestions' => []]);
            }

            $limit = $query['maxResults'] ?? 50;
            $restResponse = $locationService->searchCity($query['query'], $limit);
            $result = $restResponse['body'] ?? [];

            $suggestions = [];
            $maxSuggestions = $query['maxResults'] ?? count($result);

            for ($i = 0; $i < $maxSuggestions; ++$i) {
                if (isset($result[$i])) {
                    $entry = $result[$i];
                    $suggestions[] = [
                        'value' => $entry['postcode'].' '.$entry['name'],
                        'data'  => $entry,
                    ];
                }
            }

            $response = [
                'suggestions' => $suggestions,
            ];

            // return result as JSON
            return new JsonResponse($response);
        } catch (Exception $e) {
            return $this->handleAjaxError($e);
        }
    }
}
