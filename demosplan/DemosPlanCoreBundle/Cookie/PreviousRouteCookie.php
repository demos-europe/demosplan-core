<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Cookie;

use Carbon\Carbon;

use function str_replace;
use function strpos;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PreviousRouteCookie.
 *
 * This cookie is set to the previously accessed path and query if access fails
 * during permission checks. Subsequently, it is checked after login
 * to redirect a user where they originally wanted to go.
 *
 * To simplify subsequent processing and strip unnecessary internal information,
 * dev mode indication is removed if present.
 */
class PreviousRouteCookie extends Cookie
{
    public const NAME = 'dplan-loggedInRoute';

    public function __construct(Request $request)
    {
        $path = str_replace($request->getSchemeAndHttpHost(), '', $request->getUri());

        if (0 === strpos($path, '/app_dev.php')) {
            $path = str_replace('/app_dev.php', '', $path);
        }

        parent::__construct(static::NAME, $path, Carbon::now()->addHour());
    }
}
