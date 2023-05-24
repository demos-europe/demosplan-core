<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StoredQuery;

use DemosEurope\DemosplanAddon\Utilities\Json;

/**
 * Provide the generalized hashing functionality for stored queries.
 */
abstract class AbstractStoredQuery implements StoredQueryInterface
{
    private const INDEX_DIGEST_LENGTH = 12;
    private const DIGEST_CHARACTERS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ._';

    public function getHash(): string
    {
        return $this->digestForIndex(Json::encode($this->toJson()));
    }

    /**
     * Digest a string for use in, e.g., a MySQL index. This produces a short
     * (12-byte), case-sensitive alphanumeric string.
     *
     * This method emphasizes compactness, and should not be used for security
     * related hashing (for general purpose hashing e.g. urls).
     *
     * Took this from Phabricator and made it a sha256.
     *
     * @param string $string input string
     *
     * @return string 12-byte, case-sensitive, mostly-alphanumeric hash of
     *                the string
     */
    private function digestForIndex($string): string
    {
        $hash = hash('sha256', $string, true);

        $result = '';
        for ($ii = 0; $ii < self::INDEX_DIGEST_LENGTH; ++$ii) {
            /*
             * Mapping the hash value to the self::DIGEST_CHARACTERS
             *
             * ord($someValue) is mapping $someValue to a range from 0 - 255
             * the & 0x3F ( as bits => '11 1111') is a mask.
             * Example for ord($someValue) = 255
             *   1110 1011 (235 dec)
             * &   11 1111 (64 dec)
             * = 0010 1011 (43 dec)
             *
             * We just took all the digits from 11101011 where the mask has a 1.
             *
             * our $map has index from 0 to 63 (means 64 possible values including 0)
             * The highest possible value from the ord() function is 255.
             * When we apply the bit mask to it we get 63 (11 1111),
             * which is exactly our last index :)
             *
             */
            $result .= self::DIGEST_CHARACTERS[ord($hash[$ii]) & 0x3F];
        }

        return $result;
    }
}
