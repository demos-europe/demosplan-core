<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperInterface;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Provider\UserFromSecurityUserProvider;
use demosplan\DemosPlanCoreBundle\Validator\PasswordValidator;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class DplanAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var UrlGeneratorInterface
     */
    protected $urlGenerator;

    /**
     * @var UserPasswordHasherInterface
     */
    protected $passwordHasher;

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

    /**
     * @var ValidatorInterface
     */
    protected $passwordValidator;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(
        private readonly UserFromSecurityUserProvider $userFromSecurityUserProvider,
        UserMapperInterface $authenticator,
        GlobalConfigInterface $globalConfig,
        LoggerInterface $logger,
        MessageBagInterface $messageBag,
        PasswordValidator $passwordValidator,
        RequestStack $requestStack,
        TraceableEventDispatcher $eventDispatcher,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->userMapper = $authenticator;
        $this->eventDispatcher = $eventDispatcher;
        $this->globalConfig = $globalConfig;
        $this->logger = $logger;
        $this->passwordHasher = $passwordHasher;
        $this->passwordValidator = $passwordValidator;
        $this->requestStack = $requestStack;
        $this->urlGenerator = $urlGenerator;
        $this->messageBag = $messageBag;
        $this->translator = $translator;
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
     * that are specific to a distinct authentication like rules for
     * password strength.
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
        if (null !== $this->verificationRoute) {
            $this->logger->info('User Orga/Department needs to be verified');

            return new RedirectResponse($this->urlGenerator->generate($this->verificationRoute));
        }

        // get real User from SecurityUser that was saved in token
        $user = $this->userFromSecurityUserProvider->fromToken($token);
        $this->logger->info('User was logged in', ['id' => $user->getId(), 'roles' => implode(',', $user->getRoleCodes())]);

        // user may be split to two User objects e.g when PublicAgency user needs to have another
        // orga than the planner user (Don't blame me, it's reality)
        $publicAgencyUser = $request->getSession()->get('session2User');
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
