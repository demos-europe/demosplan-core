<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperDataportGateway;
use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

abstract class DplanAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var UrlGeneratorInterface
     */
    protected $urlGenerator;

    /**
     * @var UserMapperInterface
     */
    protected $userMapper;

    /**
     * Variable set when user needs to be redirected to a distinct verification route
     * Could be used when orga or department changed.
     *
     * @var string|null
     */
    protected $verificationRoute;

    /**
     * @var TraceableEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var MessageBagInterface
     */
    protected $messageBag;


    public function __construct(
        UserMapperInterface $authenticator,
        LoggerInterface $logger,
        MessageBagInterface $messageBag,
        TraceableEventDispatcher $eventDispatcher,
        UrlGeneratorInterface $urlGenerator,
        private readonly UserService $userService,
    ) {
        $this->userMapper = $authenticator;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
        $this->messageBag = $messageBag;
    }

    abstract protected function getCredentials(Request $request): Credentials;

    public function authenticate(Request $request): Passport
    {
        // check Honeypotfields
        try {
            $event = new RequestValidationWeakEvent($request);
            $this->eventDispatcher->post($event);
        } catch (Exception $e) {
            $this->logger->warning('Could not successfully verify Authentication form ', [$e]);

            throw new AuthenticationException('Error during authentication', 0, $e);
        }

        $credentials = $this->getCredentials($request);

        $this->validateCredentials($credentials);

        return $this->getPassport($credentials);
    }

    /**
     * This Hook might be used to validate Requirements to the credentials
     * that are specific to a distinct authentication method.
     */
    public function validateCredentials(Credentials $credentials): void
    {
    }

    /**
     * @param string $firewallName The provider (i.e. firewall) key
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // verification pages need to be loaded before logging user in
        if ($this->userMapper instanceof UserMapperDataportGateway && null !== $this->userMapper->getVerificationRoute()) {
            $this->logger->info('User Orga/Department needs to be verified');

            return new RedirectResponse($this->urlGenerator->generate($this->userMapper->getVerificationRoute()));
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            $this->logger->error('user not found', ['user' => $user]);
            throw new AuthenticationException('User not found');
        }
        $this->logger->info('User was logged in', ['id' => $user->getId(), 'roles' => $user->getDplanRolesString()]);

        // user may be split to two User objects e.g when PublicAgency user needs to have another
        // orga than the planner user (Don't blame me, it's reality)
        $publicAgencyUserId = $request->getSession()->get('session2UserId');
        $publicAgencyUser = null;
        try {
            $publicAgencyUser = $this->userService->getSingleUser($publicAgencyUserId);
        } catch (Exception) {
            // no public agency user found
        }
        if ($publicAgencyUser instanceof User) {
            $this->logger->info('User has multiple users');

            if (false === $publicAgencyUser->isProfileCompleted() || true === $publicAgencyUser->isNewUser()) {
                // Set user with incomplete profile first to be filled out
                $user = $publicAgencyUser;
            }
        }

        // propagate user login to session
        $request->getSession()->set('userId', $user->getId());

        if ($this->userNeedsProfileCompletion($user)) {
            return new RedirectResponse($this->urlGenerator->generate('DemosPlan_user_complete_data'));
        }

        // redirect user
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if (null !== $targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('core_home_loggedin'));
    }

    private function userNeedsProfileCompletion(User $user): bool
    {
        return false === $user->isProfileCompleted() || true === $user->isNewUser();
    }

    protected function getPassport(Credentials $credentials): Passport
    {
        return new SelfValidatingPassport(new UserBadge($credentials->getLogin()));
    }
}
