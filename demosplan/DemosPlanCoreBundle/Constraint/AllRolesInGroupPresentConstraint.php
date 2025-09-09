<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Constraint;

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Validator\AllRolesInGroupPresentConstraintValidator;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraint;

/**
 * When used to validate a {@link Collection} property this annotation ensures that
 * all {@link Role} entities that exist for the specified group codes are present in the collection.
 *
 * @Annotation
 */
class AllRolesInGroupPresentConstraint extends Constraint
{
    /**
     * @var string
     */
    public $message = "Necessary role with the code '{roleCode}' defined by the following group codes is missing: {groupCodes}";

    /**
     * @var array<int, string>
     */
    public $groupCodes = [];

    public function validatedBy(): string
    {
        return AllRolesInGroupPresentConstraintValidator::class;
    }
}
