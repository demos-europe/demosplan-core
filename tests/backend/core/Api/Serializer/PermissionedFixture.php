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
 * Carries every kind of permission label the {@see \demosplan\DemosPlanCoreBundle\Api\Serializer\PermissionGroupResolver}
 * has to understand.
 */
final class PermissionedFixture
{
    #[Groups(['fixture:read'])]
    public ?string $open = null;

    #[Groups(['perm:read:area_admin_statement_list'])]
    public ?string $single = null;

    #[Groups(['perm:read:area_admin_assessmenttable', 'perm:read:feature_json_api_statement'])]
    public ?string $anyOf = null;

    #[Groups(['perm:read:area_admin_assessmenttable+field_statement_memo'])]
    public ?string $allOf = null;

    #[Groups(['perm:read:field_statement_memo', 'perm:write:field_statement_memo'])]
    public ?string $readWrite = null;
}
