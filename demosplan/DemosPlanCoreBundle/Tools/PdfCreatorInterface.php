<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use Exception;

interface PdfCreatorInterface
{
    /**
     * Create a pdf from a tex template.
     *
     * @param string $content  base64 encodierte tex file
     * @param array  $pictures array of pictures ['picture0 => base64_encode(''), 'picture1' => ....]
     *
     * @throws Exception
     */
    public function createPdf(string $content, array $pictures = []): string;
}
