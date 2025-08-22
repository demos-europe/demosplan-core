<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Location;
use demosplan\DemosPlanCoreBundle\Logic\Maps\GeodatenzentrumAddressSearchService;
use demosplan\DemosPlanCoreBundle\Repository\LocationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Psr\Log\LoggerInterface;

class LocationService
{
    /**
     * @var ObjectManager
     */
    protected $em;

    public function __construct(
        ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
        private readonly GeodatenzentrumAddressSearchService $geodatenzentrumAddressSearchService,
    ) {
        $this->em = $registry->getManager();
    }

    /**
     * Get an address suggestion by typing in a street name.
     * Uses the external Geodatenzentrum API for autosuggestions.
     *
     * @param string $searchString Search query for address
     * @param int    $limit        Maximum number of results to return
     *
     * @return array Array containing search results
     */
    public function searchAddress($searchString, $limit = 20)
    {
        $logContext = [
            'service'          => 'LocationService',
            'method'           => 'searchAddress',
            'searchString'     => $searchString,
            'limit'            => $limit,
            'usingExternalApi' => true,
        ];

        $this->logger->info('Starting address search via Geodatenzentrum API', $logContext);

        try {
            $locations = $this->geodatenzentrumAddressSearchService
                ->searchAddress($searchString, $limit);

            $resultCount = count($locations);

            if (!empty($locations)) {
                $this->logger->info('Address search completed successfully with results', [
                    ...$logContext,
                    'resultCount' => $resultCount,
                    'hasResults'  => true,
                    'firstResult' => $locations[0]['name'] ?? 'unknown',
                ]);

                return ['body' => $locations];
            }

            $this->logger->info('Address search completed with no results', [
                ...$logContext,
                'resultCount' => 0,
                'hasResults'  => false,
                'note'        => 'This may be normal for very specific or non-existent addresses',
            ]);

            return ['body' => []];
        } catch (Exception $e) {
            $this->logger->error('Address search failed via Geodatenzentrum API', [
                ...$logContext,
                'error'             => $e->getMessage(),
                'errorType'         => get_class($e),
                'file'              => $e->getFile(),
                'line'              => $e->getLine(),
                'fallbackAvailable' => false,
                'troubleshooting'   => 'Check external API availability and network connectivity',
            ]);

            return ['body' => []];
        }
    }

    /**
     * Get a City by Name or postal code.
     * CRITICAL CHANGE: Now uses external Geodatenzentrum API instead of database query.
     *
     * @param string     $searchString Search query for city/location
     * @param int        $limit        Maximum number of results to return
     * @param array|null $maxExtent    Optional map extent for filtering (NOT SUPPORTED by external API)
     *
     * @return array|null Array containing search results or null on failure
     */
    public function searchCity($searchString, $limit = 20, $maxExtent = null): ?array
    {
        $logContext = [
            'service'                => 'LocationService',
            'method'                 => 'searchCity',
            'searchString'           => $searchString,
            'limit'                  => $limit,
            'maxExtent'              => $maxExtent,
            'usingExternalApi'       => true,
            'databaseSearchDisabled' => true,
        ];

        $this->logger->info('Starting city search - USING EXTERNAL API (database search disabled)', $logContext);

        if (null !== $maxExtent) {
            $this->logger->warning('Map extent filtering requested but NOT SUPPORTED by external API', [
                ...$logContext,
                'impact'           => 'Map extent parameter is ignored when using Geodatenzentrum API',
                'originalBehavior' => 'Database search supported geographic filtering via maxExtent',
                'currentBehavior'  => 'External API returns results without geographic filtering',
                'recommendation'   => 'Consider implementing client-side filtering if geographic bounds are critical',
            ]);
        }

        try {
            $locations = $this->geodatenzentrumAddressSearchService->searchAddress($searchString, $limit, $maxExtent);

            $resultCount = count($locations);

            $this->logger->info('City search completed via external API', [
                ...$logContext,
                'resultCount'   => $resultCount,
                'apiTransition' => [
                    'from'              => 'database query (LocationRepository::searchCity)',
                    'to'                => 'external Geodatenzentrum API (GeodatenzentrumAddressSearchService::searchAddress)',
                    'functionalChanges' => [
                        'mapExtentFiltering' => 'no longer available',
                        'dataSource'         => 'changed from internal database to external service',
                        'dependency'         => 'now requires external service availability',
                    ],
                ],
                'firstResult' => $resultCount > 0 ? ($locations[0]['city'] ?? 'unknown') : null,
            ]);

            return ['body' => $locations];
        } catch (Exception $e) {
            $this->logger->error('City search failed via external API', [
                ...$logContext,
                'error'           => $e->getMessage(),
                'errorType'       => get_class($e),
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
                'fallbackOptions' => [
                    'databaseFallback' => 'Not implemented - would require uncommenting repository call',
                    'currentBehavior'  => 'Return empty results on external API failure',
                    'riskAssessment'   => 'Complete service failure if external API is unavailable',
                ],
                'troubleshooting' => [
                    'immediateSteps' => [
                        'Check Geodatenzentrum API status',
                        'Verify network connectivity to external service',
                        'Review API timeout settings (currently 30s)',
                        'Check application logs for HTTP client errors',
                    ],
                    'recoveryOptions' => [
                        'Consider implementing database fallback',
                        'Monitor external service uptime',
                        'Implement circuit breaker pattern for resilience',
                    ],
                ],
            ]);

            return ['body' => []];
        }
    }

    /**
     * Get a Row by Postalcode.
     *
     * @param string $postCode
     */
    public function getSingleLocationFromPostCode($postCode): ?Location
    {
        try {
            return $this->getLocationRepository()
                ->findOneBy(['postcode' => $postCode]);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei getSingleLocationFromPostCode: ', [$e]);

            return null;
        }
    }

    /**
     * Get a Row by Radius around id.
     *
     * @param string $id
     * @param int    $radius
     *
     * @return array[]
     */
    public function getPostCodesByRadius($id, $radius): ?array
    {
        try {
            $results = $this->getLocationRepository()
                ->getPostalCodesByRadius($id, $radius);
            $postCodes = [];
            foreach ($results as $postCode) {
                $postCodes[] = $postCode['postcode'];
            }

            return $postCodes;
        } catch (Exception $e) {
            $this->logger->error('Fehler bei getIdFromPostCode: ', [$e]);

            return null;
        }
    }

    /**
     * returns a Municipal Code (AGS/GKZ) and Amtlicher Regionalschlüssel (ars)
     * by a given postalCode and locationName.
     *
     * one postalCode may have multiple locationNames with different MunicipalCodes
     *
     * @return string[]
     */
    public function getMunicipalCodes(string $postalCode, string $locationName): array
    {
        try {
            /** @var Location[] $locations */
            $locations = $this->getLocationRepository()
                ->findBy(['postcode' => $postalCode]);
            // check which location name matches best
            foreach ($locations as $location) {
                if (false !== stripos($location->getName(), $locationName)) {
                    return [
                        'municipalCode' => $location->getMunicipalCode(),
                        'ars'           => $location->getArs(),
                    ];
                }
            }
            // when no location name matches it is quite likely that the exact match
            // does not matter to the municipalCode and ars like in bigger cities
            if (0 < count($locations) && $locations[0] instanceof Location) {
                return [
                    'municipalCode' => $locations[0]->getMunicipalCode(),
                    'ars'           => $locations[0]->getArs(),
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Could not getMunicipalCodes', [$e]);
        }

        return [
            'municipalCode' => '',
            'ars'           => '',
        ];
    }

    public function findByArs(string $ars): array
    {
        return $this->getLocationRepository()->findByArs($ars);
    }

    public function findByMunicipalCode(string $municipalCode): array
    {
        return $this->getLocationRepository()->findByMunicipalCode($municipalCode);
    }

    protected function getLocationRepository(): LocationRepository
    {
        return $this->em->getRepository(Location::class);
    }
}
