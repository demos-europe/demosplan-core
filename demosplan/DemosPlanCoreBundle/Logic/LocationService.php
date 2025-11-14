<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
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
        private readonly GeodatenzentrumAddressSearchService $geocoder,
        private readonly CurrentUserInterface $currentUser,
    ) {
        $this->em = $registry->getManager();
    }

    public function searchLocation(string $query, string $limit, $maxExtent = null): array
    {
        if ($this->currentUser->hasPermission('feature_geocoder_address_search')) {
            $restResponse = $this->searchAddress($query, $limit);
        } else {
            $restResponse = $this->searchCity($query, $limit, $maxExtent);
        }

        return $restResponse['body'] ?? [];
    }

    public function getFormattedSuggestion(array $entry): array
    {
        if ($this->currentUser->hasPermission('feature_geocoder_address_search')) {
            return [
                'value' => $entry['name'].' '.$entry['housenumber'].', '.$entry['postcode'].' '.$entry['city'],
                'data'  => $entry,
            ];
        }

        return [
            'value' => $entry['postcode'].' '.$entry['name'],
            'data'  => $entry,
        ];
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
    public function searchAddress($searchString, int $limit = 20): array
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
            $locations = $this->geocoder
                ->searchAddress($searchString, $limit);

            $resultCount = count($locations);

            if ([] !== $locations) {
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
                'errorType'         => $e::class,
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
            'usingExternalApi'       => false,
            'databaseSearchDisabled' => false,
        ];

        $this->logger->info('Starting city search - using database', $logContext);

        if (null !== $maxExtent) {
            $this->logger->warning('Map extent filtering supported via database search', [
                ...$logContext,
                'impact'           => 'Map extent parameter will be used for geographic filtering',
                'dataSource'       => 'Database search supported geographic filtering via maxExtent',
                'currentBehavior'  => 'Database returns geographically filtered results',
            ]);
        }

        try {
            $dbResults = $this->getLocationRepository()->searchCity($searchString, $limit, $maxExtent);
            $locations = $this->formatDatabaseResults($dbResults ?? []);
            $resultCount = count($locations);

            $this->logger->info('City search completed database', [
                ...$logContext,
                'resultCount'    => $resultCount,
                'dataSource'     => 'internal database (LocationRepository)',
                'firstResult'    => $resultCount > 0 ? ($locations[0]['city'] ?? 'unknown') : null,
            ]);

            return ['body' => $locations];
        } catch (Exception $e) {
            $this->logger->error('City search failed via database', [
                ...$logContext,
                'error'           => $e->getMessage(),
                'errorType'       => $e::class,
                'file'            => $e->getFile(),
                'dataSource'      => 'internal database (LocationRepository)',
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

    private function formatDatabaseResults(array $dbResults): array
    {
        if ([] === $dbResults) {
            return [];
        }
        $formattedResults = [];
        foreach ($dbResults as $location) {
            $formattedResults[] = [
                'name'          => $location['name'] ?? '',
                'housenumber'   => '', // DB does not have housenumbers
                'postcode'      => $location['postcode'] ?? '',
                'city'          => $location['name'] ?? '',
                'state'         => '', // DB does not have state
                'lat'           => $location['lat'] ?? '',
                'lon'           => $location['lon'] ?? '',
                'municipalCode' => $location['municipalCode'] ?? null,
            ];
        }

        return $formattedResults;
    }
}
