<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

/**
 * Exception thrown when attempting to perform structural edits on locked segments.
 *
 * Segments become locked when they enter the assessment workflow. While text editing
 * is still allowed, structural operations (merge, split, delete, convert, reorder)
 * are prohibited to maintain assessment integrity.
 */
class EditLockedException extends Exception
{
}
