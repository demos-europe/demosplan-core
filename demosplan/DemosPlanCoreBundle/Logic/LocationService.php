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

    public function __construct(ManagerRegistry $registry, private readonly LoggerInterface $logger)
    {
        $this->em = $registry->getManager();
    }

    /**
     * Get a City by Name or postal code.
     *
     * @param string     $searchString
     * @param int        $limit
     * @param array|null $maxExtent
     */
    public function searchCity($searchString, $limit = 20, $maxExtent = null): ?array
    {
        try {
            $locations = $this->getLocationRepository()
                ->searchCity($searchString, $limit, $maxExtent);

            return ['body' => $locations];
        } catch (Exception $e) {
            $this->logger->error('Fehler bei searchCity: ', [$e]);

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
