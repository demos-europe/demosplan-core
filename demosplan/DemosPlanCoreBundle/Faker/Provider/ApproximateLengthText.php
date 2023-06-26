<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Faker\Provider;

use Closure;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Faker\Provider\Base;
use Faker\Provider\de_DE\Text;

use function strlen;

/**
 * A provider for the Faker library to generate texts of an approximate
 * length. Since things are very fluid in the faker library, the generators
 * just append text from the `text`-generator until the string length
 * is greater than the expected length. This can likely be improved if
 * necessary.
 */
class ApproximateLengthText extends Base
{
    /**
     * @var array<string,string> cached generated texts
     */
    protected $cached = [];

    /**
     * Generate a body of text of approximately the given length.
     * The precision of the text length can be increased by lowering $maxNbOfCharsPerIteration,
     * but has to 10 at least.
     *
     * @param int $length                   approximate length of text to create
     * @param int $maxNbOfCharsPerIteration Maximum number of characters the text should contain per iteration.
     *                                      Lowering this number, will be lead to more accurate length of text
     *                                      but decrease the performance.
     *
     * @return string the created Text
     */
    public function textCloseToLength(int $length, int $maxNbOfCharsPerIteration = 100, bool $useCache = false): string
    {
        $this->doSanityChecks($length, $maxNbOfCharsPerIteration);

        $generatorFunction = function ($length, $maxNbOfCharsPerIteration) {
            $text = '';
            do {
                $text .= $this->generator->text($maxNbOfCharsPerIteration);
            } while (strlen($text) < $length);

            return $text;
        };

        return $this->generateWithCache($length, $maxNbOfCharsPerIteration, $useCache, $generatorFunction);
    }

    /**
     * Generate a body of text of approximately the given length.
     * The precision of the text length can be increased by lowering $maxNbOfCharsPerIteration,
     * but has to 10 at least.
     *
     * Uses `realText` as base generator.
     */
    public function realTextCloseToLength(int $length, int $maxNbOfCharsPerIteration = 100, bool $useCache = false): string
    {
        $this->doSanityChecks($length, $maxNbOfCharsPerIteration);

        $generatorFunction = function (int $length, int $maxNbOfCharsPerIteration) {
            $textProvider = new Text($this->generator);

            $text = '';
            do {
                $text .= $textProvider->realText($maxNbOfCharsPerIteration);
            } while (strlen($text) < $length);

            return $text;
        };

        return $this->generateWithCache($length, $maxNbOfCharsPerIteration, $useCache, $generatorFunction);
    }

    protected function doSanityChecks(int $length, int $maxNbOfCharsPerIteration): void
    {
        if ($length < 1) {
            throw new InvalidArgumentException('length must be a positive integer');
        }

        if ($maxNbOfCharsPerIteration < 10) {
            throw new InvalidArgumentException('maxNbOfCharsPerIteration must be at least 10');
        }
    }

    /**
     * Handle caching of the generated text or else just return a new generated text.
     */
    protected function generateWithCache(int $length, int $maxNbOfCharsPerIteration, bool $cache, Closure $generatorFunction): string
    {
        $cacheKey = "text:{$length}:{$maxNbOfCharsPerIteration}";
        if ($cache) {
            if (!array_key_exists($cacheKey, $this->cached)) {
                $this->cached[$cacheKey] = $generatorFunction($length, $maxNbOfCharsPerIteration);
            }

            return $this->cached[$cacheKey];
        }

        return $generatorFunction($length, $maxNbOfCharsPerIteration);
    }
}
