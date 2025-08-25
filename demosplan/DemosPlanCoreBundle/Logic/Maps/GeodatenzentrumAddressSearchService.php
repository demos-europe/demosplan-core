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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class GeodatenzentrumAddressSearchService
{
    private const GEODATENZENTRUM_ADDRESS_SEARCH = 'https://sg.geodatenzentrum.de/gdz_ortssuche__353cdae2-2c78-1654-c1f0-85192cfa13d6/geosearch?count=5';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    /**
     * Search for addresses using the Geodatenzentrum search service. Enables search by street names and not only zip code.
     *
     * @param string     $query     Search query for addresses
     * @param int        $limit     Maximum number of results to return (default: 20)
     * @param array|null $maxExtent Optional map extent for filtering results (not used by external API)
     *
     * @return array Formatted address results or empty array on failure
     */
    public function searchAddress(string $query, int $limit = 20, ?array $maxExtent = null): array
    {
        $startTime = microtime(true);
        $logContext = [
            'service'      => 'GeodatenzentrumAddressSearchService',
            'method'       => 'searchAddress',
            'query'        => $query,
            'limit'        => $limit,
            'maxExtent'    => $maxExtent,
            'api_endpoint' => self::GEODATENZENTRUM_ADDRESS_SEARCH,
        ];

        // Check permissions before making external API call
        try {
            $this->currentUser->hasPermission('feature_geocoder_address_search');
        } catch (Exception $permissionException) {
            $this->logger->error('Permission denied for address search', [
                ...$logContext,
                'error'          => $permissionException->getMessage(),
                'errorType'      => 'PermissionException',
                'processingTime' => round((microtime(true) - $startTime) * 1000, 2).'ms',
            ]);
            throw $permissionException;
        }

        try {
            $requestOptions = [
                'timeout' => 30, // 30 second timeout for external API
                'query'   => [
                    'query'        => $query,
                    'format'       => 'json',
                    'limit'        => $limit,
                    'countrycodes' => 'de',
                ],
            ];

            $response = $this->httpClient->request('GET', self::GEODATENZENTRUM_ADDRESS_SEARCH, $requestOptions);
            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                $this->logger->error('Geodatenzentrum API error - non-200 status', [
                    ...$logContext,
                    'statusCode'     => $statusCode,
                    'processingTime' => round((microtime(true) - $startTime) * 1000, 2).'ms',
                ]);

                return [];
            }

            $result = $response->toArray();

            if (!isset($result['features']) || !is_array($result['features'])) {
                $this->logger->error('Geodatenzentrum API error - invalid response format', [
                    ...$logContext,
                    'responseKeys'   => array_keys($result),
                    'processingTime' => round((microtime(true) - $startTime) * 1000, 2).'ms',
                ]);

                return [];
            }

            $formattedResults = [];
            foreach ($result['features'] as $feature) {
                $formatted = $this->formatResult($feature);
                if (!empty($formatted['name']) || !empty($formatted['city'])) {
                    $formattedResults[] = $formatted;
                }
            }

            return $formattedResults;
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|DecodingExceptionInterface $e) {
            $statusCode = method_exists($e, 'getResponse') ? $e->getResponse()->getStatusCode() : 'unknown';
            $this->logger->error('Geodatenzentrum API request failed', [
                ...$logContext,
                'error'          => $e->getMessage(),
                'errorType'      => get_class($e),
                'statusCode'     => $statusCode,
                'processingTime' => round((microtime(true) - $startTime) * 1000, 2).'ms',
            ]);

            return [];
        } catch (RedirectionExceptionInterface $e) {
            // Expected redirections are ignored, service continues normally
            return [];
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error during Geodatenzentrum address search', [
                ...$logContext,
                'error'          => $e->getMessage(),
                'errorType'      => get_class($e),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'processingTime' => round((microtime(true) - $startTime) * 1000, 2).'ms',
            ]);

            return [];
        }
    }

    /**
     * Format raw Geodatenzentrum API result to match internal address format.
     * Returns data compatible with the former searchCity function.
     *
     * @param array $result Raw result from Geodatenzentrum API feature
     *
     * @return array Formatted address data with fallback values
     */
    private function formatResult(array $result): array
    {
        try {
            $properties = $result['properties'] ?? [];
            $geometry = $result['geometry'] ?? [];
            $coordinates = $geometry['coordinates'] ?? [];

            // Extract address components with safe defaults
            $street = $properties['strasse'] ?? '';
            $houseNumber = $properties['haus'] ?? '';
            $postcode = $properties['plz'] ?? '';
            $city = $properties['ort'] ?? '';
            $federalState = $properties['bundesland'] ?? '';
            $longitude = $coordinates[0] ?? null;
            $latitude = $coordinates[1] ?? null;
            $municipalCode = $properties['ags'] ?? null;

            // Validate coordinate data
            if (null !== $longitude && null !== $latitude && (!is_numeric($longitude) || !is_numeric($latitude))) {
                $longitude = null;
                $latitude = null;
            }

            return [
                'name'          => $street,
                'housenumber'   => $houseNumber,
                'postcode'      => $postcode,
                'city'          => $city,
                'state'         => $federalState,
                'lat'           => $latitude,
                'lon'           => $longitude,
                // Former searchCity function compatibility fields:
                'municipalCode' => $municipalCode,
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to format Geodatenzentrum result', [
                'service'   => 'GeodatenzentrumAddressSearchService',
                'method'    => 'formatResult',
                'error'     => $e->getMessage(),
                'errorType' => get_class($e),
                'rawResult' => $result,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            // Return safe fallback values on formatting failure
            return [
                'name'          => '',
                'housenumber'   => '',
                'postcode'      => '',
                'city'          => '',
                'state'         => '',
                'lat'           => null,
                'lon'           => null,
                'municipalCode' => null,
            ];
        }
    }
}
