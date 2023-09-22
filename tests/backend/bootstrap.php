<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

use demosplan\DemosPlanCoreBundle\Application\FrontController;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// set user and group for generated cache and log files to current user
$userId = 1001;
$tmpDir = DemosPlanPath::getTemporaryPath('dplan');
if (!mkdir($tmpDir) && !is_dir($tmpDir)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $tmpDir));
}
chown($tmpDir, $userId);
posix_setuid($userId);
posix_setgid($userId);

FrontController::bootstrap();
