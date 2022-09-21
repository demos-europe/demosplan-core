<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

if (file_exists(dirname(__DIR__).'/var/cache/prod/srcApp_KernelProdContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/prod/srcApp_KernelProdContainer.preload.php';
}

if (file_exists(dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php';
}
