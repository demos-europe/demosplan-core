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
    public function allowsPermission(string $permission): bool;

    public function hasProcedureRestriction(): bool;

    public function allowsProcedure(string $procedureId): bool;

    /**
     * `false` gates only permissions some scope lists, so incidental `area_*` checks in unrelated code
     * paths still pass — needed for a token serving general API traffic. `true` denies anything
     * unlisted, which is only safe where the reachable permissions can be enumerated in a test.
     */
    public function deniesUnlistedPermissions(): bool;
}
