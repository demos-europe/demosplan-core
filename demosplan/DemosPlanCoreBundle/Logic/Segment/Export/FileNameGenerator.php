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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

class FileNameGenerator
{
    public const PLACEHOLDER_ID = '{ID}';
    public const PLACEHOLDER_NAME = '{NAME}';
    public const PLACEHOLDER_EINGANGSNR = '{EINGANGSNR}';

    public const DEFAULT_TEMPLATE_NAME = self::PLACEHOLDER_ID.'-'.self::PLACEHOLDER_NAME.'-'.self::PLACEHOLDER_EINGANGSNR;

    public const DEFAULT_TEMPLATE_NAME_CENSORED = self::PLACEHOLDER_ID;

    private const PREFIX_SYNOPSE = 'Synopse-';
    private const PREFIX_FILTERED_SYNOPSE = 'Teilexport-Synopse-';
    private const PREFIX_SEGMENTS_EXPORT = 'Export-Abschnitte-';
    private const PREFIX_FILTERED_SEGMENTS_EXPORT = 'Teilexport-Abschnitte-';

    public function __construct(
        protected Slugify $slugify,
        protected TranslatorInterface $translator,
        private readonly NameGenerator $nameGenerator,
    ) {
    }

    /**
     * The procedure name is shortened because Windows Explorer extracts an archive into a
     * folder named after it, so its length is charged against MAX_PATH for every entry.
     */
    public function getSynopseFileName(Procedure $procedure, string $suffix, bool $isFiltered = false): string
    {
        $prefix = $isFiltered ? self::PREFIX_FILTERED_SYNOPSE : self::PREFIX_SYNOPSE;

        return $this->buildFileName($procedure, $suffix, $prefix);
    }

    public function getSegmentsExportFileName(Procedure $procedure, string $suffix, bool $isFiltered = false): string
    {
        $prefix = $isFiltered ? self::PREFIX_FILTERED_SEGMENTS_EXPORT : self::PREFIX_SEGMENTS_EXPORT;

        return $this->buildFileName($procedure, $suffix, $prefix);
    }

    private function buildFileName(Procedure $procedure, string $suffix, string $prefix): string
    {
        $procedureName = $this->nameGenerator->shortenProcedureNameForExport($procedure->getName());

        return $prefix.$this->slugify->slugify($procedureName).'.'.$suffix;
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
}
