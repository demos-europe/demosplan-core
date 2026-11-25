<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

/**
 * Result of a `statement` entry. The ids are those of the assessable copy, never the original.
 */
final readonly class OcxCreatedStatement
{
    /**
     * @param list<array{code: string, pointer: ?string, detail: ?string}> $warnings
     * @param list<string>                                                 $fileIds  delivered files the statement took over
     */
    public function __construct(
        public bool $created,
        public ?string $copyId,
        public ?string $externId,
        public array $warnings = [],
        public array $fileIds = [],
    ) {
    }
}
