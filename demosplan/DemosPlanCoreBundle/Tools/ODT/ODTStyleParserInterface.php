<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

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
    public function parseStyles(DOMDocument $dom, ?string $stylesXml = null): array;

    /**
     * Parse list styles from styles XML.
     */
    public function parseListStyles(string $stylesXml): array;
}
