<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Procedure;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string getKey()
 * @method void setKey(string $key)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getParticipationState()
 * @method void setParticipationState(string|null $participationState)
 * @method string getPermissionsSet()
 * @method void setPermissionsSet(string $permissionsSet)
 * @method string getPhaseType()
 * @method void setPhaseType(string $phaseType)
 */
class PhaseDTO extends ValueObject
{
    protected string $key;

    protected string $name;
    protected ?string $participationState;
    protected string $permissionsSet;

    protected string $phaseType;
}
