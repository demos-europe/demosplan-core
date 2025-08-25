<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Platform;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
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
     */
    #[Route(path: '/suggest/location/json', name: 'core_suggest_location_json', options: ['expose' => true])]
    public function searchLocationJsonAction(Request $request, LocationService $locationService, CurrentUserInterface $currentUser): Response
    {
        try {
            $query = $request->query->all();

            // return empty suggestions if no query set
            if (!isset($query['query']) || '' === $query['query']) {
                return new JsonResponse(['suggestions' => []]);
            }

            $limit = $query['maxResults'] ?? 50;
            if ($currentUser->hasPermission('feature_geocoder_address_search')) {
                $restResponse = $locationService->searchAddress($query['query'], $limit);
            } else {
                $restResponse = $locationService->searchCity($query['query'], $limit);
            }
            $result = $restResponse['body'] ?? [];

            $suggestions = [];
            $maxSuggestions = $query['maxResults'] ?? (is_countable($result) ? count($result) : 0);

            for ($i = 0; $i < $maxSuggestions; ++$i) {
                if (isset($result[$i])) {
                    $entry = $result[$i];
                    // check for Geocoder autosuggestions
                    if (isset($entry['name'])) {
                        $suggestions[] = [
                            'value' => $entry['name'].' '.$entry['housenumber'].' '.$entry['postcode'].' '.$entry['city'],
                            'data'  => $entry,
                        ];
                    } else {
                        // searchCity DB suggestions
                        $suggestions[] = [
                            'value' => ($entry['name'] ?? '').' '.($entry['postcode'] ?? ''),
                            'data'  => $entry,
                        ];
                    }
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
