<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

class FileNameGenerator
{
    public const PLACEHOLDER_ID = '{ID}';
    public const PLACEHOLDER_NAME = '{NAME}';
    public const PLACEHOLDER_EINGANGSNR = '{EINGANGSNR}';

    public const DEFAULT_TEMPLATE_NAME = self::PLACEHOLDER_ID.'-'.self::PLACEHOLDER_NAME.'-'.self::PLACEHOLDER_EINGANGSNR;

    public const DEFAULT_TEMPLATE_NAME_CENSORED = self::PLACEHOLDER_ID;

    public function __construct(protected Slugify $slugify, protected TranslatorInterface $translator)
    {
    }

    public function getSynopseFileName(Procedure $procedure, string $suffix): string
    {
        return 'Synopse-'.$this->slugify->slugify($procedure->getName()).'.'.$suffix;
    }

    public function getFilteredSynopseFileName(Procedure $procedure, string $suffix): string
    {
        return 'Teilexport-Synopse-'.$this->slugify->slugify($procedure->getName()).'.'.$suffix;
    }

    /**
     * Creates a file name from each given {@link Statement} to be used in the ZIP the
     * {@link Statement} is exported in.
     *
     * Initially the file name is created from the submitters name and the extern ID of the
     * statement. In case of duplicate file names, the database ID is added additionally
     * for all conflicting {@link Statement}s.
     *
     * @param array<int, array{0: Statement, 1: bool}> $statements pairs of statement and censored flag
     *
     * @return array<string, Statement>
     */
    public function mapStatementsToPathInZip(array $statements, string $fileNameTemplate = ''): array
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

    public function getFileName(Statement $statement, string $templateName = '', bool $censored = false): string
    {
        $defaultTemplateName = $censored ? self::DEFAULT_TEMPLATE_NAME_CENSORED : self::DEFAULT_TEMPLATE_NAME;
        $templateName = $templateName ?: $defaultTemplateName;

        $externalId = $this->getExternalId($statement);
        $authorSourceName = $this->getAuthorName($statement);
        $internId = $this->getInternalId($statement);

        // Replace placeholders with actual values from the $statement object
        $fileName = str_replace(
            [self::PLACEHOLDER_ID, self::PLACEHOLDER_NAME, self::PLACEHOLDER_EINGANGSNR],
            [$externalId, $authorSourceName, $internId],
            $templateName);

        return $this->slugify->slugify($fileName);
    }

    private function getAuthorName(Statement $statement): string
    {
        $orgaName = $statement->getMeta()->getOrgaName();
        $authorSourceName = $orgaName;
        if (UserInterface::ANONYMOUS_USER_NAME === $orgaName) {
            $authorSourceName = $statement->getUserName();
        }
        if (null === $authorSourceName || '' === trim($authorSourceName)) {
            $authorSourceName = $this->translator->trans('statement.name_source.unknown');
        }

        return $authorSourceName;
    }

    private function getInternalId(Statement $statement): string
    {
        $internId = $statement->getInternId();
        if (null === $internId || '' === trim($internId)) {
            return $this->translator->trans('statement.intern_id.unknown');
        }

        return $internId;
    }

    private function getExternalId(Statement $statement): string
    {
        $externId = $statement->getExternId();
        if (null === $externId || '' === trim($externId)) {
            return $this->translator->trans('statement.extern_id.unknown');
        }

        return $externId;
    }

    /**
     * Creates a file name from the given {@link Statement}.
     *
     * The file name is created from the submitters name and the extern ID of the statement.
     * If the trimmed extern ID is an empty string it will not be included in the result.
     * Optionally the database ID of the statement can be included too to ensure uniqueness.
     *
     * While the extern ID is set in normal parenthesis (`(1234)`), the database ID is set
     * in square brackets (`[abcd-ef12-…]`). This avoids confusion on the users part for
     * the case that the extern ID is an empty string and the database ID is included in
     * the result.
     */
    private function getPathInZip(
        Statement $statement,
        bool $withDbId,
        string $fileNameTemplate = '',
        bool $censored = false,
    ): string {
        $dbId = $statement->getId();
        $fileName = $this->getFileName($statement, $fileNameTemplate, $censored);

        return $withDbId
            ? "$fileName-$dbId.docx"
            : "$fileName.docx";
    }
}
