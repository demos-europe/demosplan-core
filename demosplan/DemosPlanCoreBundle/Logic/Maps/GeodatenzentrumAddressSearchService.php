<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Maps;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeodatenzentrumAddressSearchService /*
 * Geodatenzentrum returns the following json data format:
 * [
 * {
 * "address": {
 * "strasse": "Unter den Linden",
 * "hausnummer": "77",
 * "postleitzahl": "10117",
 * "stadt": "Berlin",
 * "bundesland": "Berlin"
 * },
 * "lat": 52.5170365,
 * "lon": 13.3888599
 * // ... other fields
 * }
 * // ... more results
 * ]
 */
{
    private const GEODATENZENTRUM_ADDRESS_SEARCH = 'https://sg.geodatenzentrum.de/gdz_ortssuche__353cdae2-2c78-1654-c1f0-85192cfa13d6/geosearch?count=5';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * search for addresses using the Geodatenzentrum search service. Enables search by street names and not only zip code.
     *
     * @param string $query // search query
     * @param int    $limit // max number of results to return
     */
    public function searchAddress($query, $limit = 20): array
    {
        try {
            $response = $this->httpClient->request('GET', self::GEODATENZENTRUM_ADDRESS_SEARCH, [
                'query' => [
                    'query'       => $query,
                    'format'      => 'json',
                    'limit'       => $limit,
                    'countrycodes'=> 'de',
                ],
            ]);
            $url = $response->getInfo('url');
            $result = $response->toArray();

            return array_map([$this, 'formatResult'], $result['features']);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei searchAddress: ', [$e]);

            return [];
        }
    }

    // returning not only data from Geodatenzentrum, but also data to match searchCity function requests
    private function formatResult(array $result): array
    {
        $addressData = $result['properties'];
        $address = $addressData['text'] ?? [];
        $street = $addressData['strasse'] ?? '';
        $houseNumber = $addressData['haus'] ?? '';
        $postcode = $addressData['plz'] ?? '';
        $city = $addressData['ort'] ?? '';
        $federalState = $addressData['bundesland'] ?? '';
        $longitude = $result['geometry']['coordinates'][0] ?? null;
        $latitude = $result['geometry']['coordinates'][1] ?? null;
        $municipalCode = $addressData['ags'] ?? null;

        $suggestion = [
            'name'          => $street,
            'housenumber'   => $houseNumber,
            'postcode'      => $postcode,
            'city'          => $city,
            'state'         => $federalState,
            'lat'           => $latitude,
            'lon'           => $longitude,
            // former searchCity function compatibility fields:
            'municipalCode' => $municipalCode,
        ];

        return $suggestion;
    }
}
