<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

class FakeDataGeneratorFactory
{
    /**
     * Fakeable file extensions.
     *
     * @const array<int,string>|string[]
     */
    public const FAKEABLE_EXTENSIONS = ['bmp', 'docx', 'jpeg', 'jpg', 'mp4', 'pdf', 'png', 'zip'];

    /**
     * @var array<string,DataGeneratorInterface>|DataGeneratorInterface[]
     */
    private $generators;

    public function __construct(iterable $generators)
    {
        $this->generators = [];

        foreach ($generators as $generator) {
            $this->generators[$generator->getFileExtension()] = $generator;
        }
    }

    public function getFormat(string $format): DataGeneratorInterface
    {
        if ('jpeg' === $format) {
            $format = 'jpg';
        }

        if (!array_key_exists($format, $this->generators)) {
            throw new InvalidArgumentException('Invalid format');
        }

        return $this->generators[$format];
    }
}
