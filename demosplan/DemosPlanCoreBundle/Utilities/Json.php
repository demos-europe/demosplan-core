<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities;

use DemosEurope\DemosplanAddon\Contracts\JsonInterface;
use const JSON_ERROR_NONE;
use demosplan\DemosPlanCoreBundle\Exception\JsonException;
use function json_decode;
use function json_encode;
use function json_last_error;

final class Json implements JsonInterface
{
    /**
     * Encode to Json with error checking.
     *
     * Please be aware that this method will *NOT* return false
     * if the encode fails as it will throw a {@see JsonException}
     * instead.
     *
     * @param mixed $data
     *
     * @throws JsonException
     */
    public static function encode(
        $data,
        ?int $flags = 0
    ): string {
        $encoded = json_encode($data, $flags);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonException::encodeFailed();
        }

        return $encoded;
    }

    /**
     * Decode json content to an associative array.
     *
     * This tries to decode a json object string into an associative array.
     * If that fails, it will throw a {@see JsonException}.
     *
     * @throws JsonException
     */
    public static function decodeToArray(string $json, ?int $flags = 0): array
    {
        return self::decode($json, true, $flags);
    }

    /**
     * Decode json content to the matching native type.
     *
     * This tries to decode json content to it's matching native type.
     * If that fails, it will throw a {@see JsonException}.
     *
     * @return array|object|int|bool|string
     *
     * @throws JsonException
     */
    public static function decodeToMatchingType(string $json, ?int $flags = 0)
    {
        return self::decode($json, false, $flags);
    }

    /**
     * @return array|object|int|bool|string
     */
    private static function decode(string $json, bool $associative, int $flags)
    {
        $data = json_decode($json, $associative, 512, $flags);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonException::decodeFailed();
        }

        return $data;
    }
}
