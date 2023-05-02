<?php
declare(strict_types=1);

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
     * @return string
     *
     * @throws Exception
     */
    public function createPdf(string $content, array $pictures = []): string;
}
