<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentExporterFileNameGenerator
{
    protected TranslatorInterface $translator;

    protected Slugify $slugify;

    public const PLACEHOLDER_ID = '{ID}';
    public const PLACEHOLDER_NAME = '{NAME}';
    public const PLACEHOLDER_EINGANGSNR = '{EINGANGSNR}';

    public const DEFAULT_TEMPLATE_NAME = self::PLACEHOLDER_ID.'-'.self::PLACEHOLDER_NAME.'-'.self::PLACEHOLDER_EINGANGSNR;

    public function __construct(
        Slugify $slugify,
        TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->slugify = $slugify;
    }

    public function getFileName(Statement $statement, string $templateName = ''): string
    {
        $defaultTemplateName = self::DEFAULT_TEMPLATE_NAME;
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
