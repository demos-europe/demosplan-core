<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Filesystem;

/**
 * This filesystem is intended for all files that are local to the demosplan instance
 * and not bound to any procedure / user.
 */
final class LocalFilesystem extends FilesystemFlysystemAdapter
{
}
