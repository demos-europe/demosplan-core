<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;

use function in_array;

/**
 * Request-scoped snapshot of the integration token that authenticated the current request, frozen at
 * authentication time.
 *
 * Differs from {@see \demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext}
 * in the two ways that matter: the procedure restriction always applies, and
 * {@see self::deniesUnlistedPermissions()} is true, so the scopes are the complete list of what the
 * request may do rather than a filter over an indexed subset.
 */
final readonly class ProcedureIntegrationTokenContext implements ApiTokenContextInterface
{
    public const REQUEST_ATTRIBUTE = '_procedure_integration_token_context';

    /**
     * @param list<string> $scopePermissions union of permissions implied by the token's scopes
     */
    public function __construct(
        public ProcedureIntegrationToken $token,
        public array $scopePermissions,
    ) {
    }

    public static function fromToken(ProcedureIntegrationToken $token): self
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

    /**
     * Always restricted — the token pins exactly one procedure and cannot exist without it.
     */
    public function hasProcedureRestriction(): bool
    {
        return true;
    }

    public function allowsProcedure(string $procedureId): bool
    {
        return $this->token->allowsProcedure($procedureId);
    }

    /**
     * The token reaches one purpose-built endpoint, so the permissions it can touch are enumerable
     * and asserted by that endpoint's test. That makes denying everything unlisted safe here, and it
     * is what keeps the token's rights from following whatever its principal's role happens to grant.
     */
    public function deniesUnlistedPermissions(): bool
    {
        return true;
    }
}
