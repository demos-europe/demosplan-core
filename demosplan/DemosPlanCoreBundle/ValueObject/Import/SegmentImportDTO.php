<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Import;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lightweight DTO for segment validation during Excel import.
 * Memory-efficient alternative to full Doctrine entities during validation phase.
 */
class SegmentImportDTO
{
    /**
     * @param int         $rowNumber      Excel row number for error reporting
     * @param string      $statementId    Parent statement ID
     * @param string      $internId       Internal segment ID (must be unique)
     * @param string|null $externId       External segment ID
     * @param string      $recommendation Segment recommendation text
     * @param string|null $tags           Comma-separated tags
     * @param string|null $places         Comma-separated places
     * @param string|null $counties       Comma-separated counties
     * @param string|null $municipalities Comma-separated municipalities
     * @param string|null $priorityAreas  Comma-separated priority areas
     */
    public function __construct(
        public readonly int $rowNumber,
        #[Assert\NotBlank(message: 'Stellungnahme ID darf nicht leer sein')]
        public readonly string $statementId,
        #[Assert\Length(max: 255)]
        public readonly string $internId,
        #[Assert\Length(max: 255)]
        public readonly ?string $externId,
        public readonly string $recommendation,
        public readonly ?string $tags,
        public readonly ?string $places,
        public readonly ?string $counties,
        public readonly ?string $municipalities,
        public readonly ?string $priorityAreas,
    ) {
    }
}
