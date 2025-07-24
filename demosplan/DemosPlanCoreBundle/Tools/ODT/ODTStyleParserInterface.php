<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT;

use DOMDocument;

/**
 * Interface for ODT style parsing operations.
 */
interface ODTStyleParserInterface
{
    /**
     * Parse ODT styles from content and styles XML.
     */
    public function parseStyles(DOMDocument $dom): array;

    /**
     * Parse list styles from styles XML.
     */
    public function parseListStyles(string $stylesXml): array;
}
