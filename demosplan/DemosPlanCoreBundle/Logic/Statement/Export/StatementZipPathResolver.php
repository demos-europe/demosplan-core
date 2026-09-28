<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\Export;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;

readonly class StatementZipPathResolver
{
    public function __construct(private FileNameGenerator $fileNameGenerator)
    {
    }

    /**
     * Resolves a unique ZIP path for each given {@link Statement}.
     *
     * Initially the path is built from the submitter name and extern ID. When two statements
     * produce the same path, the database ID is appended to both conflicting entries to
     * ensure uniqueness. Non-conflicting statements keep the shorter path.
     *
     * @param array<int, array{0: Statement, 1: bool}> $statements pairs of statement and censored flag
     *
     * @return array<string, Statement>
     */
    public function resolve(array $statements, string $fileNameTemplate = ''): array
    {
        $pathedStatements = [];
        $previousKeysOfReaddedDuplicates = [];
        foreach ($statements as [$statement, $censored]) {
            $pathInZip = $this->getPathInZip($statement, false, $fileNameTemplate, $censored);
            // in case of a duplicate, add the database ID to the name
            if (array_key_exists($pathInZip, $pathedStatements)) {
                $duplicate = $pathedStatements[$pathInZip];
                $previousKeysOfReaddedDuplicates[$pathInZip] = $pathInZip;
                $duplicateExtendedPathInZip = $this->getPathInZip($duplicate, true, $fileNameTemplate);
                $pathedStatements[$duplicateExtendedPathInZip] = $duplicate;
                $pathInZip = $this->getPathInZip($statement, true, $fileNameTemplate);
            }

            if (array_key_exists($pathInZip, $pathedStatements)) {
                throw new InvalidArgumentException('duplicated statement given');
            }

            $pathedStatements[$pathInZip] = $statement;
        }

        // Remove old keys of duplicates only after the previous loop has completed,
        // as otherwise a third duplicate would be added to the result array without
        // the extended path.
        foreach ($previousKeysOfReaddedDuplicates as $key) {
            unset($pathedStatements[$key]);
        }

        return $pathedStatements;
    }

    /**
     * Builds the ZIP path for a single {@link Statement}.
     *
     * Optionally the database ID is appended to disambiguate statements that share
     * the same base name.
     */
    private function getPathInZip(
        Statement $statement,
        bool $withDbId,
        string $fileNameTemplate = '',
        bool $censored = false,
    ): string {
        $dbId = $statement->getId();
        $fileName = $this->fileNameGenerator->getFileName($statement, $fileNameTemplate, $censored);

        return $withDbId
            ? "$fileName-$dbId.docx"
            : "$fileName.docx";
    }
}
