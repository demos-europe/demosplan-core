<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\User\LoginAudit;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\LoginAuditRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Builds and persists LoginAudit rows. Audit failures must never break the actual
 * login flow, so all exceptions from the EntityManager are caught and logged at
 * critical level — a write storm becomes visible in the alert pipeline without
 * crashing user-facing authentication.
 *
 * Trade-off: a flush() failure here leaves the default EntityManager in a closed
 * state for the rest of the request. Other listeners that write to the same EM
 * after our event (e.g. UserLoginSubscriber's setLastLogin update on
 * AuthenticationSuccessEvent) would silently fail in that case. The risk is
 * accepted because the audit insert is small and unlikely to fail; if it ever
 * becomes a recurring issue, move the audit writes to a dedicated EntityManager.
 */
class LoginAuditWriter
{
    public function __construct(
        private readonly LoginAuditRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(
        string $result,
        ?User $user,
        string $authenticator,
        ?Request $request,
        ?string $failureReason = null,
    ): void {
        try {
            $truncatedAuthenticator = $this->truncate($authenticator, 191);
            $sessionIdHash = $this->hashSessionId($request);

            // Deduplicate repeated success events within the same session — the
            // firewall may re-fire LoginSuccessEvent on subsequent requests of an
            // already-authenticated session. Failures are always recorded.
            if (LoginAudit::RESULT_SUCCESS === $result
                && null !== $sessionIdHash
                && $this->repository->existsSuccessForSessionAndAuthenticator($sessionIdHash, $truncatedAuthenticator)
            ) {
                return;
            }

            $audit = new LoginAudit($result, $truncatedAuthenticator);
            $audit->setUserId($user?->getId());
            $audit->setFailureReason(null === $failureReason ? null : $this->truncate($failureReason, 255));
            $audit->setUserAgent($this->extractUserAgent($request));
            $audit->setSessionIdHash($sessionIdHash);

            $this->repository->persistAndFlush($audit);
        } catch (Throwable $e) {
            $this->logger->critical('Failed to persist login audit row', [
                'exception'     => $e,
                'result'        => $result,
                'authenticator' => $authenticator,
            ]);
        }
    }

    private function truncate(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    private function extractUserAgent(?Request $request): ?string
    {
        if (!$request instanceof Request) {
            return null;
        }

        $agent = (string) $request->headers->get('User-Agent', '');

        return '' === $agent ? null : $this->truncate($agent, 512);
    }

    /**
     * `hasSession()` is used to avoid implicitly starting a session via `getSession()`.
     */
    private function hashSessionId(?Request $request): ?string
    {
        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            return null;
        }

        $id = $session->getId();
        if ('' === $id) {
            return null;
        }

        return hash('sha256', $id);
    }
}
