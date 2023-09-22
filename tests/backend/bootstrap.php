<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

use demosplan\DemosPlanCoreBundle\Application\FrontController;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// set user and group for generated cache and log files to current user
posix_setuid(1001);
posix_setgid(1001);

FrontController::bootstrap();
