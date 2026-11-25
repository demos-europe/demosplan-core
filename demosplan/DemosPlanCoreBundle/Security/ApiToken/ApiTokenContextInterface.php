<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\ApiToken;

/**
 * Restricts the permissions of a request that a machine client authenticated.
 *
 * Deny by default: the permission evaluator grants a permission only if the user has it and
 * {@see self::allowsPermission()} lists it.
 */
interface ApiTokenContextInterface
{
    public const REQUEST_ATTRIBUTE = '_api_token_context';

    public function allowsPermission(string $permission): bool;
}
