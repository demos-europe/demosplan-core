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
 * Carries every kind of group the {@see \demosplan\DemosPlanCoreBundle\Api\Serializer\FieldPermissionResolver}
 * has to understand: a group that is a permission name is a permission group, anything else is not.
 */
final class PermissionedFixture
{
    #[Groups(['fixture:read'])]
    public ?string $open = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $single = null;

    #[Groups(['area_admin_assessmenttable', 'feature_json_api_statement'])]
    public ?string $anyOf = null;

    #[Groups(['area_admin_assessmenttable+field_statement_memo'])]
    public ?string $allOf = null;

    #[Groups(['field_statement_memo'])]
    public ?string $memo = null;

    /** Not a permission name, e.g. a typo: the resolver must never treat it as a permission. */
    #[Groups(['field_statement_memoo'])]
    public ?string $unrecognised = null;
}
