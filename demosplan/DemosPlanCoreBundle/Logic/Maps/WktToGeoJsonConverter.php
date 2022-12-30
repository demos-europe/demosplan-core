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

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use geoPHP;
use GuzzleHttp\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use stdClass;

class WktToGeoJsonConverter
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function convertIfNeeded(string $input): string
    {
        try {
            $features = $this->getValidInput($input);
            if (!$this->isWkt($features)) {
                return empty($features) ? '' : Json::encode($features);
            }

            $geoJsonClass = new stdClass();
            $geoJsonClass->type = 'FeatureCollection';
            $geoJsonClass->features = [];

            foreach ($features as $feature) {
                $featureClass = new stdClass();
                $featureClass->type = 'Feature';
                $featureClass->properties = new stdClass();
                $geoPhp = geoPHP::load($feature, 'wkt');
                $featureClass->geometry = Json::decodeToMatchingType($geoPhp->out('json'));

                $geoJsonClass->features[] = $featureClass;
            }

            $features = Json::encode($geoJsonClass);

            $this->logger->debug(
                sprintf('FeatureCollection Transformed: %s', $features)
            );

            return $features;
        } catch (InvalidArgumentException|JsonException $e) {
            $this->logger->error('Received string cannot be converted to geoJson.', [$input]);
        }

        return '';
    }

    /**
     * @param mixed $input
     */
    private function isWkt($input): bool
    {
        return is_array($input);
    }

    /**
     * @return mixed
     *
     * @throws JsonException
     */
    private function getValidInput(string $geoJson)
    {
        $this->logger->debug('FeatureCollection Input: '.$geoJson);
        $geoJson = str_replace('bobject123', '', $geoJson);
        $geoJson = urldecode($geoJson);

        return Json::decodeToMatchingType($geoJson);
    }
}
