<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHasher;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\BuilderInterface;
use Endroid\QrCodeBundle\Response\QrCodeResponse;
use Exception;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

use function in_array;

/**
 * Class DemosPlanAuthenticationController.
 *
 * Contains all actions which are closely connected to setting, checking, resetting the user authentication, or setting
 * it all up by registering new users.
 * Also contains pages that are directly linked to such activities, like confirmation pages.
 */
class DemosPlanUserAuthenticationController extends DemosPlanUserController
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var UserHandler
     */
    protected $userHandler;

    public function __construct(
        UserHandler $userHandler,
        UserService $userService,
    ) {
        $this->userHandler = $userHandler;
        $this->userService = $userService;
    }

    /**
     * Passwort ändern.
     *
     * @DplanPermissions("area_mydata_password")
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_change_password', path: '/password/change', options: ['expose' => true])]
    public function changePasswordAction(Request $request)
    {
        $requestPostFields = collect($request->request->all())->only(
            [
                'userId',
                'password_old',
                'password_new',
                'password_new_2',
            ]
        )->toArray();

        $this->userHandler->changePasswordHandler($requestPostFields);

        return $this->redirectToRoute('DemosPlan_user_portal');
    }

    /**
     * Request change of email.
     * Send Mail to verify change of E-Mail-Address.
     *
     * @DplanPermissions("feature_change_own_email")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_change_email_request', path: '/email/change')]
    public function changeEmailRequestAction(Request $request, PasswordHasherFactoryInterface $hasherFactory)
    {
        $requestPostFields = collect($request->request->all())->only(
            ['userId', 'password', 'newEmail'])->toArray();

        $this->userHandler->requestEmailChange(
            $requestPostFields['userId'],
            $requestPostFields['password'],
            $requestPostFields['newEmail'],
            $hasherFactory
        );

        return $this->redirectToRoute('DemosPlan_user_portal');
    }

    #[Route(path: '/authentication/2fa/qr-code', name: 'DemosPlan_user_qr_code')]
    #[\demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions('feature_2fa')]
    public function displayGoogleAuthenticatorQrCode(BuilderInterface $builder, TotpAuthenticatorInterface $totpAuthenticator)
    {
        $qrCodeContent = $totpAuthenticator->getQRContent($this->getUser());
        $result = $builder
            ->size(200)
            ->margin(20)
            ->data($qrCodeContent)
            ->validateResult(true)
            ->build();

        return new QrCodeResponse($result);
    }

    #[Route(path: '/authentication/2fa/enable', name: 'DemosPlan_user_2fa_enable')]
    #[\demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions('feature_2fa')]
    public function enable2fa(
        CurrentUserInterface $currentUser,
        EntityManagerInterface $entityManager,
        TotpAuthenticatorInterface $totpAuthenticator,
    ): RedirectResponse {
        $user = $currentUser->getUser();
        if (!$user->isTotpEnabled()) {
            $user->setTotpSecret($totpAuthenticator->generateSecret());
            $entityManager->flush();
        }

        return $this->redirectToRoute('DemosPlan_user_portal');
    }

    #[Route(path: '/authentication/2faemail/enable', name: 'DemosPlan_user_2fa_email_enable')]
    #[\demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions('feature_2fa')]
    public function enable2faemail(
        CodeGeneratorInterface $codeGenerator,
        CurrentUserInterface $currentUser,
    ): RedirectResponse {
        $user = $currentUser->getUser();
        if (!$user->isEmailAuthEnabled()) {
            $codeGenerator->generateAndSend($user);
        }

        return $this->redirectToRoute('DemosPlan_user_portal');
    }

    #[Route(path: '/authentication/2faemail/send', name: 'DemosPlan_user_2fa_email_send')]
    #[\demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions('feature_2fa')]
    public function send2faemail(
        CodeGeneratorInterface $codeGenerator,
        CurrentUserInterface $currentUser,
    ): RedirectResponse {
        $codeGenerator->reSend($currentUser->getUser());

        return $this->redirectToRoute('DemosPlan_user_portal');
    }

    /**
     * Set email address of user. Called via link which was sent to user via email.
     *
     * @DplanPermissions("feature_change_own_email")
     */
    #[Route(name: 'DemosPlan_user_doubleoptin_change_email', path: 'email/change/doubleoptin/{uId}/{key}')]
    public function changeEmailConfirmationAction(string $uId, string $key): RedirectResponse
    {
        try {
            // the actual change of the email address:
            $user = $this->userHandler->getSingleUser($uId);
            if (!$user instanceof User) {
                return $this->redirectToRoute('core_home');
            }
            $user = $this->userHandler->changeEmailValidate($user, $key);

            if ($user instanceof User) {
                // Invalidate the token to prevent reuse by setting lastLogin to current time
                $user->setLastLogin(new DateTime());
                // Save the user through the user service
                $this->userService->updateUserObject($user);
                $this->getMessageBag()->add('confirm', 'confirm.email.changed', ['emailAddress' => $user->getEmail()]);

                return $this->redirectToRoute('DemosPlan_user_portal');
            }

            $this->getMessageBag()->add('error', 'error.email.changed');
        } catch (Exception) {
            // Fehler wurden schon geloggt, generischer Fehler wird ausgegeben
        }

        return $this->redirectToRoute('core_home_loggedin');
    }

    /**
     *  @DplanPermissions({"area_demosplan","feature_password_recovery"})
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_user_password_recover', path: '/password/recover', options: ['expose' => true])]
    public function recoverPasswordAction(RateLimiterFactory $userRegisterLimiter, Request $request)
    {
        $requestPost = $request->request;

        if ($requestPost->has('email')) {
            $email = $requestPost->get('email');
            if (is_string($email)) {
                // avoid brute force attacks
                $limiter = $userRegisterLimiter->create($request->getClientIp());
                if (false === $limiter->consume()->isAccepted()) {
                    $this->messageBag->add('warning', 'warning.user.pass.reset.throttle');

                    return $this->redirectToRoute('core_home');
                }

                $user = $this->userService->getUserByFields(['email' => $email]);

                if (0 === count($user)) {
                    $this->logger->error(
                        "Couldn't find distinct user with given Email address for recover",
                        ['email' => $email, 'found' => count($user)]
                    );
                    $this->messageBag->add('warning', 'error.user.login');

                    return $this->redirectToRoute('core_home');
                }

                if (reset($user) instanceof User && null === reset($user)->getPassword()) {
                    $this->messageBag->add('warning', 'error.user.registration.password');

                    return $this->redirectToRoute('core_home');
                }

                $this->userHandler->recoverPasswordHandler(reset($user));
            }
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/password_recover.html.twig',
            [
                'title'        => 'user.password.recover',
                'templateVars' => [],
            ]
        );
    }

    /**
     * This Action is only needed to define the routes.
     * Authentication is handled via guards located in Security/Authentication.
     */
    #[Route(name: 'DemosPlan_user_login_gateway', path: '/redirect/')]
    #[Route(name: 'DemosPlan_user_login_osi_legacy', path: '/user/login/osi/legacy')]
    #[Route(name: 'DemosPlan_user_login', path: '/user/login', options: ['expose' => true])]
    public function loginAction(CurrentUserInterface $currentUser, LoggerInterface $logger): RedirectResponse
    {
        // this possibly never is never reached, but better safe than sorry
        $this->logger->warning('Something weird happened, is guard authentication up and running?');
        try {
            if (!$currentUser->getUser() instanceof AnonymousUser) {
                return $this->redirectToRoute('core_home_loggedin');
            }
        } catch (Throwable) {
            // do nothing as this would equal default action return value
        }

        return $this->redirectToRoute('core_home');
    }

    /**
     * Alternatives Loginform auf einer ganzen Seite.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return Response
     *
     * @throws AccessDeniedException|Exception
     */
    #[Route(name: 'DemosPlan_user_login_alternative', path: '/dplan/login', options: ['expose' => true])]
    public function alternativeLoginAction(
        CacheInterface $cache,
        CurrentUserInterface $currentUser,
        CustomerService $customerService,
        ParameterBagInterface $parameterBag,
        Request $request,
    ) {
        if (!($currentUser->getUser() instanceof AnonymousUser)) {
            return $this->redirectToRoute('core_home_loggedin');
        }

        // Check whether login via form is enabled
        if (!$this->getGlobalConfig()->isAlternativeLoginEnabled()) {
            throw new AccessDeniedException();
        }

        $users = [];
        $currentCustomer = $customerService->getCurrentCustomer()->getSubdomain();
        $availableCustomers = $customerService->getReservedCustomerNamesAndSubdomains();
        $customers = array_map(static fn (array $availableCustomer): string => $availableCustomer[1], $availableCustomers);
        $usersOsi = [];
        $customerKey = $customerService->getCurrentCustomer()->getSubdomain();
        $useLoginListIdp = false;

        if (true === $parameterBag->get('alternative_login_use_testuser')) {
            // collect users for Login as
            $users = $cache->get('login_testuser_list'.$customerKey,
                function (ItemInterface $item) use ($parameterBag) {
                    $item->expiresAfter(UserRepository::LOGIN_LIST_CACHE_DURATION);

                    $testPassword = $parameterBag->get('alternative_login_testuser_defaultpass');

                    return $this->userService->getTestUsers($testPassword);
                });

            // add access to test external identity provider
            // do not display link when it targets same site
            $gatewayUrl = $parameterBag->get('gateway_url');
            $useLoginListIdp = '' !== $gatewayUrl && !str_contains($gatewayUrl, $request->getPathInfo());
        }

        if (true === $parameterBag->get('alternative_login_use_testuser_osi')) {
            $usersOsi = $cache->get('login_testuser_list_osi'.$customerKey, function (ItemInterface $item) {
                $item->expiresAfter(UserRepository::LOGIN_LIST_CACHE_DURATION);

                return $this->userService->getTestUsersOsi($this->globalConfig->getProjectFolder());
            });
        }

        $useIdp = false;
        // this check needs to be reworked once we know better how to save oauth parameters by customer
        if ('' !== $parameterBag->get('oauth_client')
            && 'bb' === $customerService->getCurrentCustomer()->getSubdomain()) {
            $useIdp = true;
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/alternative_login.html.twig',
            [
                'title'           => 'user.login',
                'useIdp'          => $useIdp,
                'customers'       => $customers,
                'currentCustomer' => $currentCustomer,
                'loginList'       => [
                    'enabled'  => 0 < count($users) || 0 < count($usersOsi),
                    'useIdp'   => $useLoginListIdp,
                    'users'    => $users,
                    'usersOsi' => $usersOsi,
                ],
            ]
        );
    }

    /**
     * Logout via security system.
     */
    #[Route(name: 'DemosPlan_user_logout', path: '/user/logout')]
    public function logoutAction(): void
    {
        // special cases are handled by the LogoutSubscriber
    }

    /**
     * Dislay logout landing page.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_user_logout_success', path: '/user/logout/success')]
    public function logoutSuccessAction(PermissionsInterface $permissions)
    {
        try {
            if (!$permissions->hasPermission('feature_has_logout_landing_page')) {
                return $this->redirectToRoute('core_home');
            }

            return $this->renderTemplate('@DemosPlanCore/DemosPlanUser/logout_success.html.twig');
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_doubleoptin_invite_confirmation', path: '/doubleoptin/{uId}/{token}')]
    public function confirmInvitationAction(UserHasher $userHasher, string $token, string $uId)
    {
        try {
            $user = $this->getUserWithCertainty($uId);
            $this->checkIsUserAllowedToChangePassword($user, $userHasher, $token);
        } catch (InvalidArgumentException) {
            return $this->redirectToRoute('DemosPlan_user_login_alternative');
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/user_set_password.html.twig',
            [
                'token' => $token,
                'uId'   => $uId,
            ]
        );
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_password_set', path: '/user/{uId}/setpass/{token}', options: ['expose' => true])]
    public function setPasswordAction(
        FlashMessageHandler $flashMessageHandler,
        LoginFormAuthenticator $loginFormAuthenticator,
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        UserHasher $userHasher,
        UserService $userService,
        string $token,
        string $uId,
    ) {
        try {
            $newPassword = $request->request->get('password');
            $newPassword2 = $request->request->get('password_new_2');

            if (null === $newPassword || $newPassword !== $newPassword2 || '' === $newPassword) {
                $this->messageBag->add('warning', 'error.user.password.not.identical');

                throw new InvalidArgumentException('Password not identical or absent', 2);
            }

            $user = $this->getUserWithCertainty($uId);
            $this->checkIsUserAllowedToChangePassword($user, $userHasher, $token);

            $error = $this->userHandler->checkMandatoryErrorsPasswordStrength($newPassword);
            if (0 !== count($error)) {
                $flashMessageHandler->setFlashMessages($error);

                throw new InvalidArgumentException('Password too weak', 4);
            }

            $userService->changePassword($uId, '', $newPassword, false);
            $this->userHandler->setAccessConfirmed($user);

            // Invalidate the token to prevent reuse by setting lastLogin to current time
            $user->setLastLogin(new DateTime());
            $this->userService->updateUserObject($user);

            $this->messageBag->add('confirm', 'user.password.set');

            // login user
            return $userAuthenticator->authenticateUser($user, $loginFormAuthenticator, $request);
        } catch (InvalidArgumentException $exception) {
            // set redirect according to exception thrown in this method
            if (in_array($exception->getCode(), [2, 4], true)) {
                return $this->redirectToRoute('DemosPlan_user_doubleoptin_invite_confirmation', ['uId' => $uId, 'token' => $token]);
            }

            return $this->redirectToRoute('core_home');
        }
    }

    private function getUserWithCertainty(string $uId): User
    {
        $user = $this->userService->getSingleUser($uId);
        if (!$user instanceof User) {
            $this->messageBag->add('warning', 'error.user.registration.password');
            $this->logger->warning('Could not find User to set Password', ['uId' => $uId]);

            throw new InvalidArgumentException('User invalid', 1);
        }

        return $user;
    }

    private function checkIsUserAllowedToChangePassword(User $user, UserHasher $userHasher, string $token): void
    {
        // Password should only be set when hash is valid
        if (!$userHasher->isValidPasswordEditHash($user, $token)) {
            $this->messageBag->add('warning', 'error.user.password.not.allowed');

            throw new InvalidArgumentException('Password not allowed to set', 3);
        }
    }
}
