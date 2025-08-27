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
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class GeodatenzentrumAddressSearchService implements GeocoderInterface
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

        // First step: validate permissions before making external API call
        // Second: make call to external API provider
        // Third: format and filter results
        try {
            $this->validatePermissions();
            $rawFeatures = $this->makeApiCall($query, $limit);
            $formattedResults = $this->formatResult($rawFeatures);
            $this->logSuccess($logContext, $startTime, count($formattedResults));

            return $formattedResults;
        } catch (Exception $e) {
            $this->logError($e, $logContext, $startTime);
            if (str_contains($e->getMessage(), 'Permission')) {
                throw $e;
            }

            return [];
        } catch (Throwable $e) {
            $this->logError($e, $logContext, $startTime);

            return [];
        }
    }

    // Function to check if user has permission to use the Geodatenzentrum API call
    private function validatePermissions(): void
    {
        $this->currentUser->hasPermission('feature_geocoder_address_search');
    }

    // make call to external API via HttpClient
    private function makeApiCall(string $query, int $limit): array
    {
        $requestOptions = [
            'timeout'      => 30,
            'query'        => [
                'query'        => $query,
                'limit'        => $limit,
                'format'       => 'json',
                'countrycodes' => 'de',
            ],
        ];
        try {
            $response = $this->httpClient->request('GET', self::GEODATENZENTRUM_ADDRESS_SEARCH, $requestOptions);
            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                $this->logger->error('Geodatenzentrum API request failed', [
                    'statusCode'     => $statusCode,
                    'query'          => $query,
                ]);
                throw new RuntimeException('Invalid API response');
            }
            $result = $response->toArray();

            if (!isset($result['features']) || !is_array($result['features'])) {
                $this->logger->error('Geodatenzentrum API request failed', [
                    'responseKeys'   => array_keys($result),
                    'query'          => $query,
                ]);
                throw new RuntimeException('Invalid API response');
            }

            return $result['features'];
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|DecodingExceptionInterface $e) {
            throw new RuntimeException('API request failed: '.$e->getMessage(), 0, $e);
        } catch (RedirectionExceptionInterface $e) {
            // Expected redirections are ignored, service continues normally
            return [];
        }
    }

    private function formatResult(array $rawFeature): array
    {
        $formattedResults = [];
        foreach ($rawFeature as $feature) {
            $formatted = $this->formatSingleResult($feature);
            if (!empty($formatted['name']) || !empty($formatted['city'])) {
                $formattedResults[] = $formatted;
            }
        }

        return $formattedResults;
    }

    /**
     * @param array $result Raw result from Geodatenzentrum API feature
     *
     * @return array Formatted address data with fallback values
     */
    private function formatSingleResult(array $result): array
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

    private function logSuccess(array $logContext, float $startTime, int $resultCount): void
    {
        $this->logger->info('Address search completed successfully', [
            ...$logContext,
            'resultCount'    => $resultCount,
            'processingTime' => round((microtime(true) - $startTime) * 1000, 2).'ms',
        ]);
    }

    private function logError(Throwable $e, array $logContext, float $startTime): void
    {
        $this->logger->error('Address search failed', [
            ...$logContext,
            'error'          => $e->getMessage(),
            'errorType'      => get_class($e),
            'file'           => $e->getFile(),
            'line'           => $e->getLine(),
            'processingTime' => round((microtime(true) - $startTime) * 1000, 2).'ms',
        ]);
    }
}
