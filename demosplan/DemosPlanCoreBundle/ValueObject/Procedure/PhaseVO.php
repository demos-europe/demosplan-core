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
 * @method void   setKey(string $key)
 * @method string getName()
 * @method void   setName(string $name)
 * @method string getParticipationState()
 * @method void   setParticipationState(string|null $participationState)
 * @method string getPermissionsSet()
 * @method void   setPermissionsSet(string $permissionsSet)
 * @method string getPhaseType()
 * @method void   setPhaseType(string $phaseType)
 */
class PhaseVO extends ValueObject
{
    final public const PROCEDURE_PHASE_NAME = 'name';
    final public const PROCEDURE_PHASE_KEY = 'key';
    final public const PROCEDURE_PHASE_PERMISSIONS_SET = 'permissionset';
    final public const PROCEDURE_PHASE_PARTICIPATION_STATE = 'participationstate';

    protected string $key;

    protected string $name;
    protected ?string $participationState;
    protected string $permissionsSet;

    protected string $phaseType;
}
