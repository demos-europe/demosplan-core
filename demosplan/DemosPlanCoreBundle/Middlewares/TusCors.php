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
use TusPhp\Middleware\TusMiddleware;
use TusPhp\Request;
use TusPhp\Response;

class TusCors implements TusMiddleware
{
    public function handle(Request $request, Response $response)
    {
        $headers = $response->getHeaders();

        $headers['Access-Control-Allow-Headers'] .= ', '.Header::FILE_HASH.', '.Header::FILE_ID;
        $headers['Access-Control-Expose-Headers'] .= ', '.Header::FILE_HASH.', '.Header::FILE_ID;

        $response->replaceHeaders($headers);
    }
}
