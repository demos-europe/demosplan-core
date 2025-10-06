<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Validates map extent boundaries to ensure they contain the initial viewport.
 */
class MapExtentValidator
{
    public function __construct(
        private readonly MessageBagInterface $messageBag,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validates that the map extent (boundaries) fully contains the initial viewport (bounding box).
     *
     * @param array|null $mapExtent   The maximum extent boundaries [minX, minY, maxX, maxY]
     * @param array|null $boundingBox The initial map viewport [minX, minY, maxX, maxY]
     *
     * @throws InvalidArgumentException if the bounding box is not fully contained within the map extent
     */
    public function validateExtentContainsBoundingBox(?array $mapExtent, ?array $boundingBox): void
    {
        // Skip validation if either is null or empty
        if (null === $mapExtent || null === $boundingBox || [] === $mapExtent || [] === $boundingBox) {
            return;
        }

        // Convert to flat arrays if they are in coordinate format
        $flatMapExtent = $this->convertToFlatArray($mapExtent);
        $flatBoundingBox = $this->convertToFlatArray($boundingBox);

        // Need exactly 4 values for each
        if (4 !== count($flatMapExtent) || 4 !== count($flatBoundingBox)) {
            return;
        }

        [$extentMinX, $extentMinY, $extentMaxX, $extentMaxY] = $flatMapExtent;
        [$boxMinX, $boxMinY, $boxMaxX, $boxMaxY] = $flatBoundingBox;

        // Check if bounding box is fully contained within map extent
        if ($boxMinX < $extentMinX
            || $boxMinY < $extentMinY
            || $boxMaxX > $extentMaxX
            || $boxMaxY > $extentMaxY
        ) {
            $this->messageBag->add('error', 'error.map.viewport.outside_extent');

            $this->logger->error('The starting map section (bounding box) must be completely within the map extent. MapExtent: ['.implode(', ', $flatMapExtent).'], BoundingBox: ['.implode(', ', $flatBoundingBox).']');

            throw new InvalidArgumentException('Der Startkartenausschnitt (boundingBox) muss vollständig innerhalb der Kartenbegrenzung (mapExtent) liegen. MapExtent: ['.implode(', ', $flatMapExtent).'], BoundingBox: ['.implode(', ', $flatBoundingBox).']');
        }
    }

    /**
     * Converts coordinate format to flat array if needed.
     *
     * @param array $coordinates Either flat [minX, minY, maxX, maxY] or structured ['start' => [...], 'end' => [...]]
     *
     * @return array Flat array [minX, minY, maxX, maxY]
     */
    private function convertToFlatArray(array $coordinates): array
    {
        // Already flat format
        if (isset($coordinates[0]) && is_numeric($coordinates[0])) {
            return array_values($coordinates);
        }

        // Structured format with 'start' and 'end'
        if (isset($coordinates['start'], $coordinates['end'])) {
            $start = $coordinates['start'];
            $end = $coordinates['end'];

            return [
                $start['latitude'] ?? $start[0] ?? 0,
                $start['longitude'] ?? $start[1] ?? 0,
                $end['latitude'] ?? $end[0] ?? 0,
                $end['longitude'] ?? $end[1] ?? 0,
            ];
        }

        return [];
    }
}
