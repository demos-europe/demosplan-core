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
 * Lightweight DTO for statement validation during Excel import.
 * Memory-efficient alternative to full Doctrine entities during validation phase.
 */
class StatementImportDTO
{
    /**
     * @param int         $rowNumber       Excel row number for error reporting
     * @param string      $statementId     Statement ID from Excel
     * @param string|null $externId        External ID
     * @param string      $submitterName   Name of submitter
     * @param string      $submitterType   Type of submitter (Bürger, Behörde, etc.)
     * @param string|null $street          Street address
     * @param string|null $postalCode      Postal code
     * @param string|null $city            City
     * @param string|null $email           Email address
     * @param string|null $phone           Phone number
     * @param string      $text            Statement text (concatenated from segments)
     * @param string      $publicStatement Public or internal statement
     * @param int         $segmentCount    Number of segments for this statement
     */
    public function __construct(
        public readonly int $rowNumber,
        #[Assert\NotBlank(message: 'Stellungnahme ID darf nicht leer sein')]
        public readonly string $statementId,
        #[Assert\Length(max: 255)]
        public readonly ?string $externId,
        #[Assert\Length(max: 255)]
        public readonly string $submitterName,
        public readonly string $submitterType,
        #[Assert\Length(max: 255)]
        public readonly ?string $street,
        #[Assert\Length(max: 20)]
        public readonly ?string $postalCode,
        #[Assert\Length(max: 255)]
        public readonly ?string $city,
        #[Assert\Email(message: 'Ungültige E-Mail-Adresse')]
        #[Assert\Length(max: 255)]
        public readonly ?string $email,
        #[Assert\Length(max: 50)]
        public readonly ?string $phone,
        public readonly string $text,
        public readonly string $publicStatement,
        #[Assert\GreaterThan(0, message: 'Stellungnahme muss mindestens ein Segment haben')]
        public readonly int $segmentCount,
    ) {
    }
}
