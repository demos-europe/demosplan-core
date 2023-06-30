<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use Symfony\Component\HttpFoundation\File\File;

interface DocxImporterInterface
{
    /**
     * Import a docx file.
     */
    public function importDocx(File $file, string $elementId, string $procedure, string $category): array;
}
