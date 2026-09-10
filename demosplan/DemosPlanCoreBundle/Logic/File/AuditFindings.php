<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\File;

/**
 * Counts findings of one kind while keeping at most a fixed number of examples.
 */
class AuditFindings
{
    private int $count = 0;

    /** @var list<array<string, string>> */
    private array $samples = [];

    public function __construct(private readonly int $sampleLimit)
    {
    }

    /**
     * @param array<string, string> $finding
     */
    public function add(array $finding): void
    {
        ++$this->count;
        if (count($this->samples) < $this->sampleLimit) {
            $this->samples[] = $finding;
        }
    }

    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return list<array<string, string>>
     */
    public function getSamples(): array
    {
        return $this->samples;
    }
}
