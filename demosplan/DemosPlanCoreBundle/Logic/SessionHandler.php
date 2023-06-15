<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class SessionHandler extends PdoSessionHandler
{
    public function __construct(ParameterBagInterface $parameterBag)
    {
        // configure PdoSessionHandler
        $dsn = 'mysql:host='.$parameterBag->get('database_host').';dbname='.$parameterBag->get('database_name');
        $pdoOptions = [
            'db_username'  => $parameterBag->get('database_user'),
            'db_password'  => $parameterBag->get('database_password'),
        ];
        parent::__construct($dsn, $pdoOptions);
    }

    /**
     * Clear all Sessiondata of current user.
     */
    public function logoutUser(Request $request): void
    {
        // If it's desired to kill the session, also delete the session cookie.
        // Note: This will destroy the session, and not just the session data!
        $request->getSession()->invalidate();
    }
}
