<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\LoginAudit;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\LoginAuditWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Persists every primary authentication outcome and every 2FA outcome to the
 * login_audit table. Logout is intentionally not recorded.
 */
class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoginAuditWriter $writer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Subscribing to scheb's per-provider SUCCESS event was intentionally dropped
        // because it produces a duplicate audit row alongside COMPLETE — the latter
        // is the one that signifies the user is fully authenticated.
        return [
            LoginSuccessEvent::class                  => 'onLoginSuccess',
            LoginFailureEvent::class                  => 'onLoginFailure',
            TwoFactorAuthenticationEvents::FAILURE    => 'onTwoFactorFailure',
            TwoFactorAuthenticationEvents::COMPLETE   => 'onTwoFactorComplete',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ($this->isStatelessRequest($event->getRequest())) {
            return;
        }

        $user = $event->getUser();

        $this->writer->record(
            LoginAudit::RESULT_SUCCESS,
            $user instanceof User ? $user : null,
            $this->describeAuthenticator($event->getAuthenticator()),
            $event->getRequest(),
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if ($this->isStatelessRequest($event->getRequest())) {
            return;
        }

        $this->writer->record(
            LoginAudit::RESULT_FAILURE,
            null,
            $this->describeAuthenticator($event->getAuthenticator()),
            $event->getRequest(),
            $event->getException()->getMessageKey(),
        );
    }

    /**
     * Stateless firewalls (JWT API authentication) re-fire LoginSuccessEvent on
     * every authenticated request; conceptually that is token validation, not a
     * login event. We skip those — the real login is the token-issuance call,
     * which goes through a stateful firewall and is audited separately.
     */
    private function isStatelessRequest(Request $request): bool
    {
        return !$request->hasSession();
    }

    public function onTwoFactorFailure(TwoFactorAuthenticationEvent $event): void
    {
        $this->recordTwoFactor($event, LoginAudit::RESULT_FAILURE, '2fa_invalid_code', '2fa');
    }

    public function onTwoFactorComplete(TwoFactorAuthenticationEvent $event): void
    {
        $this->recordTwoFactor($event, LoginAudit::RESULT_SUCCESS, null, '2fa_complete');
    }

    private function recordTwoFactor(
        TwoFactorAuthenticationEvent $event,
        string $result,
        ?string $reason,
        string $authenticator,
    ): void {
        $tokenUser = $event->getToken()->getUser();
        $user = $tokenUser instanceof User && !$tokenUser instanceof FunctionalUser ? $tokenUser : null;

        $this->writer->record(
            $result,
            $user,
            $authenticator,
            $event->getRequest(),
            LoginAudit::RESULT_FAILURE === $result ? $reason : null,
        );
    }

    /**
     * Returns the authenticator's fully-qualified class name. The FQN is stored
     * verbatim so core/addon authenticators that share a short class name remain
     * distinguishable in audit data.
     */
    private function describeAuthenticator(AuthenticatorInterface $authenticator): string
    {
        return $authenticator::class;
    }
}
