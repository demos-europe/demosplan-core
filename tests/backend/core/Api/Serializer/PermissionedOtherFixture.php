<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Api\Serializer;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Stands in for a separate input or output class of a resource.
 */
final class PermissionedOtherFixture
{
    #[Groups(['area_admin_statement_list'])]
    public ?string $onlyHere = null;
}
