<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken;

use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;

/**
 * Request-scoped snapshot of the PAT that authenticated the current request.
 *
 * Attached to the request attributes under {@see self::REQUEST_ATTRIBUTE} by
 * {@see PersonalAccessTokenRequestAuthenticator}, and consumed by the Permissions evaluator to
 * intersect the user's effective permissions with the token's scopes. Frozen at authentication time,
 * so a later scope or revocation change does not affect the request in flight.
 */
final readonly class PersonalAccessTokenContext implements ApiTokenContextInterface
{
    public const REQUEST_ATTRIBUTE = '_pat_context';

    /**
     * @param list<string> $scopePermissions union of feature_* permissions implied by the token's scopes
     */
    public function __construct(
        public PersonalAccessToken $token,
        public array $scopePermissions,
    ) {
    }

    public static function fromToken(PersonalAccessToken $token): self
    {
        return new self(
            $token,
            PersonalAccessTokenScope::unionPermissions($token->getScopes()),
        );
    }

    public function allowsPermission(string $permission): bool
    {
        return in_array($permission, $this->scopePermissions, true);
    }

    public function hasProcedureRestriction(): bool
    {
        return null !== $this->token->getProcedureIds();
    }

    public function allowsProcedure(string $procedureId): bool
    {
        $allowed = $this->token->getProcedureIds();

        return null === $allowed || in_array($procedureId, $allowed, true);
    }

    /**
     * A PAT authenticates general API traffic, so only permissions some scope enumerates are gated.
     * Denying everything unlisted would trip incidental checks in code paths no scope describes.
     */
    public function deniesUnlistedPermissions(): bool
    {
        return false;
    }
}
