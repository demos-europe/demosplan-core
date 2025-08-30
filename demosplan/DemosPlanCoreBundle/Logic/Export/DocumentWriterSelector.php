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

class DocumentWriterSelector
{
    public function __construct(
        private readonly PermissionsInterface $permissions,
    ) {
    }

    public function getWriterType(): string
    {
        return $this->permissions->hasPermission('feature_export_odt_instead_of_docx')
            ? 'ODText'
            : 'Word2007';
    }

    public function getFileExtension(): string
    {
        return $this->permissions->hasPermission('feature_export_odt_instead_of_docx')
            ? '.odt'
            : '.docx';
    }

    public function getContentType(): string
    {
        return $this->permissions->hasPermission('feature_export_odt_instead_of_docx')
            ? 'application/vnd.oasis.opendocument.text'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function isOdtFormat(): bool
    {
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
