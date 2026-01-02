<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Middlewares;

use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Logic\HeaderSanitizerService;
use TusPhp\Middleware\TusMiddleware;
use TusPhp\Request;
use TusPhp\Response;

class TusCors implements TusMiddleware
{
    public function __construct(
        private readonly HeaderSanitizerService $headerSanitizer,
    ) {
    }

    public function handle(Request $request, Response $response)
    {
        $headers = $response->getHeaders();

        // Safely append our file hash and file ID headers
        $allowHeaders = $this->headerSanitizer->sanitizeHeader($headers['Access-Control-Allow-Headers']);
        $exposeHeaders = $this->headerSanitizer->sanitizeHeader($headers['Access-Control-Expose-Headers']);

        $headers['Access-Control-Allow-Headers'] = $allowHeaders.', '.Header::FILE_HASH.', '.Header::FILE_ID;
        $headers['Access-Control-Expose-Headers'] = $exposeHeaders.', '.Header::FILE_HASH.', '.Header::FILE_ID;

        $response->replaceHeaders($headers);
    }
}
