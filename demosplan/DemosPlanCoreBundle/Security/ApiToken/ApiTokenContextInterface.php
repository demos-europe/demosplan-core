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
 * What the permission evaluator needs to know about the API token that authenticated the current
 * request, whatever kind of token it is.
 *
 * A token can only ever narrow the authenticated user's rights, never widen them: the evaluator
 * checks the user's own grant first and consults the context afterwards.
 */
interface ApiTokenContextInterface
{
    /**
     * Whether the token's scopes imply the given permission.
     */
    public function allowsPermission(string $permission): bool;

    public function hasProcedureRestriction(): bool;

    public function allowsProcedure(string $procedureId): bool;

    /**
     * Whether a permission the token's scopes do not mention must be denied.
     *
     * `false` gates only permissions that appear in some scope's list and lets everything else
     * through — necessary for a token serving general API traffic, where unrelated code paths
     * perform incidental `area_*` checks that no scope enumerates.
     *
     * `true` denies anything unlisted. Only safe for a token whose reachable code is narrow enough
     * that the permissions it touches can be enumerated and asserted in a test.
     */
    public function deniesUnlistedPermissions(): bool;
}
