<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Statement\Processor\Patch;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a user may send to change a statement (PATCH of the Statement resource).
 *
 * A permission name on a property is the permission the user needs to send it. The serializer does
 * not filter incoming data (the Patch declares no groups for it and PermissionContextBuilder leaves
 * it alone), so every sent property reaches the StatementPatchProcessor. The processor checks the
 * permission and rejects the request with a 403 instead of dropping the property silently. A
 * property without a permission name can be sent by everyone who may update statements.
 * See {@see \demosplan\DemosPlanCoreBundle\Api\Serializer\FieldPermissionResolver}.
 *
 * A property that is not sent is `null`. The order matters: the submit date is applied before the
 * authored date, which may not be later than it.
 *
 * The permissions mirror what the EDT StatementResourceType required for updating.
 */
final class UpdateStatement
{
    #[Groups(['field_statement_memo'])]
    public ?string $memo = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $internId = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $authorName = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $submitName = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $initialOrganisationName = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $initialOrganisationDepartmentName = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $initialOrganisationStreet = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $initialOrganisationHouseNumber = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $initialOrganisationPostalCode = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $initialOrganisationCity = null;

    #[Groups(['area_admin_statement_list'])]
    public ?string $submitType = null;

    #[Groups(['area_admin_statement_list'])]
    #[Assert\DateTime(format: DATE_ATOM)]
    public ?string $submitDate = null;

    #[Groups(['area_admin_statement_list'])]
    #[Assert\DateTime(format: DATE_ATOM)]
    public ?string $authoredDate = null;
}
