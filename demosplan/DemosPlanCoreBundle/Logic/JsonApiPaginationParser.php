<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Psr\Log\LoggerInterface;

/**
 * Parses the pagination array from a JsonApi Request and
 * provides it as APIPagination object.
 *
 * @see APIPagination
 */
class JsonApiPaginationParser
{
    final public const DEFAULT_PAGE_SIZE = 10;
    final public const MAX_PAGE_SIZE = 1000;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function parseApiPaginationProfile(
        array $profile,
        string $sortString,
        int $defaultSize = self::DEFAULT_PAGE_SIZE,
    ): APIPagination {
        if (0 >= $defaultSize) {
            $defaultSize = self::DEFAULT_PAGE_SIZE;
        }

        $page = new APIPagination();

        $size = $this->getRequestProfilePositiveInt($profile, 'size', $defaultSize);
        $page->setSize($size);
        $number = $this->getRequestProfilePositiveInt($profile, 'number', 1);
        $page->setNumber($number);
        $page->setSortString($sortString);

        return $page->lock();
    }

    protected function getRequestProfilePositiveInt(array $profile, string $key, int $default): int
    {
        if (array_key_exists($key, $profile) && is_string($profile[$key])) { // check type before using intval
            $intValue = (int) $profile[$key];
            if (0 < $intValue) {
                // Enforce maximum page size limit for security
                if ('size' === $key && $intValue > self::MAX_PAGE_SIZE) {
                    $this->logger->warning('JsonAPI: Large page size requested ({requested}), limited to {max}', [
                        'requested' => $intValue,
                        'max'       => self::MAX_PAGE_SIZE,
                    ]);

                    return self::MAX_PAGE_SIZE;
                }

                return $intValue;
            }
        }

        return $default;
    }
}
