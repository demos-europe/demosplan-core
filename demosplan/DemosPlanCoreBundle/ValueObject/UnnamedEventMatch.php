<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use Stringable;

/**
 * Used to store various information as string of (event) classes found in any directories.
 */
class UnnamedEventMatch implements Stringable
{
    public function __construct(
        protected readonly string $filePath,
        protected readonly string $nameSpace,
        protected readonly ?string $matchingParent,
    ) {
    }

    public function toArray(): array
    {
        return [
            'filePath'          => $this->filePath,
            'nameSpace'         => $this->nameSpace,
            'matchingParent'    => $this->matchingParent,
        ];
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function __toString(): string
    {
        return $this->nameSpace;
    }
}
