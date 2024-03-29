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

class CellExportStyle
{
    /**
     * @param array<string, mixed> $cellStyle
     * @param array<string, mixed> $paragraphStyle
     * @param array<string, mixed> $fontStyle
     */
    public function __construct(private readonly int $width, private readonly array $cellStyle = [], private readonly array $paragraphStyle = [], private readonly array $fontStyle = [])
    {
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCellStyle(): array
    {
        return $this->cellStyle;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParagraphStyle(): array
    {
        return $this->paragraphStyle;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFontStyle(): array
    {
        return $this->fontStyle;
    }
}
