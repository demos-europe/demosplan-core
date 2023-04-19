<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Maps;

use demosplan\DemosPlanCoreBundle\Exception\ExternalDataFetchException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\LocationService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\ValueObject\LocationData;
use demosplan\DemosPlanCoreBundle\ValueObject\MapCoordinate;
use Geocoder\Provider\Provider;
use Geocoder\Query\ReverseQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MapCoordinateDataFetcher
{
    /**
     * @var Provider
     */
    private $nominatim;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LocationService
     */
    private $locationService;

    /**
     * @var MapProjectionConverter
     */
    private $mapProjectionConverter;

    public function __construct(
        LocationService $locationService,
        LoggerInterface $logger,
        MapProjectionConverter $mapProjectionConverter,
        Provider $dplanGeocoder
    ) {
        $this->nominatim = $dplanGeocoder;
        $this->logger = $logger;
        $this->locationService = $locationService;
        $this->mapProjectionConverter = $mapProjectionConverter;
    }

    /**
     * @throws InvalidDataException
     */
    public function fetchCoordinateData(MapCoordinate $mapCoordinate): LocationData
    {
        $procedureLocationData = new LocationData();
        [$lon, $lat] = $this->mapProjectionConverter->convertPoint([
                $mapCoordinate->getLatitude(),
                $mapCoordinate->getLongitude(),
            ],
            $this->mapProjectionConverter->getProjection($mapCoordinate->getCrs()),
            $this->mapProjectionConverter->getProjection(MapService::EPSG_4326_PROJECTION_LABEL)
        );

        $this->validatePoint($lat, $lon);

        try {
            $addressCollection = $this->nominatim->reverseQuery(
                ReverseQuery::fromCoordinates($lat, $lon)
            );
        } catch (Throwable $e) {
            $this->logger->error('Could not fetch Data from Nominatim', ['coordinate' => $mapCoordinate, $e]);
            throw ExternalDataFetchException::fetchFailed(Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }

        $postalCode = null;
        $locationName = null;
        if ($addressCollection->has(0)) {
            $address = $addressCollection->get(0);
            $locationName = $address->getLocality();
            $postalCode = $address->getPostalCode();
        }

        $procedureLocationData->setPostalCode($postalCode);
        $procedureLocationData->setLocality($locationName);

        // fetch municipalCode from Opengeodb as it is much more reliable
        // than trying to parse them from OSM
        if (null !== $postalCode && '' !== $postalCode && null !== $locationName && '' !== $locationName) {
            $municipalCodes = $this->locationService->getMunicipalCodes($postalCode, $locationName);
            $procedureLocationData->setMunicipalCode($municipalCodes['municipalCode'] ?? '');
            $procedureLocationData->setArs($municipalCodes['ars'] ?? '');
        }

        $procedureLocationData->lock();

        return $procedureLocationData;
    }

    /**
     * @param float|int $lat
     * @param float|int $lon
     *
     * @throws InvalidDataException
     */
    private function validatePoint($lat, $lon): void
    {
        $isValidLatitude = $this->isInRange($lat, -90.0, 90.0);
        $isValidLongitude = $this->isInRange($lon, -180.0, 180.0);
        if (!$isValidLatitude || !$isValidLongitude) {
            throw new InvalidDataException('Coordinate not valid');
        }
    }

    /**
     * Check whether some value is within a range of given values.
     *
     * @param float|int $value
     * @param float|int $min
     * @param float|int $max
     */
    protected function isInRange($value, $min, $max): bool
    {
        return $min <= $value && $value <= $max;
    }
}
