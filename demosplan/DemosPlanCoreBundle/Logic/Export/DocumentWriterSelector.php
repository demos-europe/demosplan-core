<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\Export\Odt\OdtBorderedWriter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class DocumentWriterSelector
{
    public function __construct(
        private readonly PermissionsInterface $permissions,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getWriterType(): string
    {
        return $this->shouldUseOdt()
            ? 'ODText'
            : 'Word2007';
    }

    /**
     * PhpWord's stock ODText writer drops table-cell borders, so wrap it with
     * {@see OdtBorderedWriter} which post-processes content.xml to add them.
     */
    public function createWriter(PhpWord $phpWord): WriterInterface
    {
        if ($this->shouldUseOdt()) {
            return new OdtBorderedWriter($phpWord);
        }

        return IOFactory::createWriter($phpWord, $this->getWriterType());
    }

    public function getFileExtension(): string
    {
        return $this->shouldUseOdt()
            ? '.odt'
            : '.docx';
    }

    public function getContentType(): string
    {
        return $this->shouldUseOdt()
            ? 'application/vnd.oasis.opendocument.text'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function isOdtFormat(): bool
    {
        return $this->shouldUseOdt();
    }

    private function shouldUseOdt(): bool
    {
        // Priority: explicit format from request attributes > permission-based fallback
        $request = $this->requestStack->getCurrentRequest();
        $explicitFormat = $request?->attributes->get('export_format');

        if (null !== $explicitFormat) {
            return 'odt' === $explicitFormat;
        }

        return $this->permissions->hasPermission('feature_export_odt_instead_of_docx');
    }

    public function getTableStyleForFormat(array $tableStyle): array
    {
        // ODT uses same PhpWord styling as DOCX - format differences handled by writer
        return $tableStyle;
    }

    public function getCellStyleForFormat(array $cellStyle): array
    {
        // ODT uses same PhpWord styling as DOCX - format differences handled by writer
        return $cellStyle;
    }
}
