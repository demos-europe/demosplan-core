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

interface DataGeneratorInterface
{
    /**
     * Return the byte string of the generated data.
     */
    public function generate(int $approximateSizeInBytes): string;

    /**
     * The file extension of the generated data.
     */
    public function getFileExtension(): string;
}
