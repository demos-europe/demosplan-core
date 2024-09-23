<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\LanguageSwitchRequestEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\EmailAddressInUseException;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\LoginNameInUseException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\SendMailException;
use demosplan\DemosPlanCoreBundle\Exception\UserAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\LinkMessageSerializable;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\SessionHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementAnonymizeService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\AddressBookEntryService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use demosplan\DemosPlanCoreBundle\ValueObject\User\AddressBookEntryVO;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanUserController extends BaseController
{
    public function __construct(private readonly CurrentUserService $currentUser, private readonly ParameterBagInterface $parameterBag)
    {
    }

    /**
     * Daten vervollständigen.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_complete_data', path: '/willkommen')]
    public function newUserCompleteDataAction(
        MailService $mailService,
        OrgaService $orgaService,
        Request $request,
        SessionHandler $sessionHandler,
        UserHandler $userHandler,
    ) {
        $orga = $orgaService->getOrga($this->currentUser->getUser()->getOrganisationId());
        $subdomain = $this->getGlobalConfig()->getSubdomain();
        if (!$orga instanceof Orga) {
            return $this->redirectToRoute('core_home', ['status' => 'missingOrgadata']);
        }
        $roles = $this->currentUser->getUser()->getRoles();
        $this->getLogger()->info(
            'Welcomepage for Orga '.DemosPlanTools::varExport($orga->getName(), true).' '.DemosPlanTools::varExport($orga->getId(), true)
        );
        $this->getLogger()->info('Welcomepage Roles '.DemosPlanTools::varExport($roles, true));

        $templateVars = $this->checkProfileCompleted();

        // sind alle notwendigen Organisationsdaten hinterlegt?
        $orgadataMissing = false;

        // Firmenkunde
        if (in_array(OrgaType::PUBLIC_AGENCY, $orga->getTypes($subdomain), true)) {
            $this->getLogger()->info('Welcomepage is'.OrgaType::PUBLIC_AGENCY);
            if (null == $orga->getEmail2() || '' === $orga->getEmail2()) {
                $this->getLogger()->info('Welcomepage Orgadata Email2 missing');
                $orgadataMissing = true;
            }
        }
        // Kommune oder Planungsbüro
        if (in_array(OrgaType::MUNICIPALITY, $orga->getTypes($subdomain), true)
            || in_array(OrgaType::PLANNING_AGENCY, $orga->getTypes($subdomain), true)) {
            $this->getLogger()->info('Welcomepage is ', ['types' => $orga->getTypes($subdomain)]);
        }

        // Überprüfe die Orgadaten nur bei Fachplanern und Institutionen
        $skipOrgadataRoles = [
            Role::PUBLIC_AGENCY_SUPPORT,
            Role::CITIZEN,
            Role::PLATFORM_SUPPORT,
            Role::BOARD_MODERATOR,
            Role::CONTENT_EDITOR,
            Role::PROCEDURE_CONTROL_UNIT,
            Role::PLANNING_SUPPORTING_DEPARTMENT,
            Role::PROCEDURE_DATA_INPUT,
        ];

        if (1 === count($roles) && in_array($roles[0], $skipOrgadataRoles, true)) {
            $orgadataMissing = false;
        }

        if (!$orgadataMissing) {
            $this->getLogger()->info('Welcomepage save orgadata completed');
            // Speichere bei dem User, dass die Organisationsdaten mittlerweile ausgefüllt wurden
            $currentUser = $this->currentUser->getUser();

            if (User::ANONYMOUS_USER_ID !== $currentUser->getId()) {
                $data = [
                    'email'            => $currentUser->getEmail(), // Pflichtfeld beim Update
                    'firstname'        => $currentUser->getFirstname(), // Pflichtfeld beim Update
                    'lastname'         => $currentUser->getLastname(), // Pflichtfeld beim Update
                    'newUser'          => false,
                    'profileCompleted' => true,
                    'access_confirmed' => true,
                ];

                $updatedUser = null;
                try {
                    $updatedUser = $userHandler->updateUser($currentUser->getId(), $data);
                } catch (Exception $e) {
                    $this->getLogger()->warning('Update user failed with exception', [$e]);
                }

                if (!$updatedUser instanceof User) {
                    // logout user to avoid eternal redirect loop
                    $this->getLogger()->warning('Update user failed, perform logout', [$data]);
                    $this->messageBag->add('warning', 'warning.login.welcomepage.failed');

                    return $this->redirectToRoute('DemosPlan_user_logout');
                }
                $this->getLogger()->info('Welcomepage redirect to index loggedin');
            }

            return $this->redirectToRoute('core_home_loggedin');
        }

        // wenn noch nicht alle Daten gesetzt sind
        $userRoles = $this->currentUser->getUser()->getRoles();
        // Institutions-Sachbearbeitung und FachplanerSachbearbeitung werden nicht reingelassen
        $isSachbearbeiterOnly = false;
        // Is it a worker(*Sachbearbeiter) without admin role?
        if (in_array(Role::PLANNING_AGENCY_WORKER, $userRoles)
            || in_array(Role::PUBLIC_AGENCY_WORKER, $userRoles)
            || in_array(Role::HEARING_AUTHORITY_WORKER, $userRoles)) {
            $this->getLogger()->info('Welcomepage isSachbearbeiterOnly FP');
            $isSachbearbeiterOnly = true;
        }
        // Is it a worker(*Sachbearbeiter) without coordination role?
        if (in_array(Role::PLANNING_AGENCY_ADMIN, $userRoles)
            || in_array(Role::PUBLIC_AGENCY_COORDINATION, $userRoles)
            || in_array(Role::HEARING_AUTHORITY_ADMIN, $userRoles)) {
            $this->getLogger()->info('Welcomepage isSachbearbeiterOnly Toeb');
            $isSachbearbeiterOnly = false;
        }

        // Schicke die nicht zuständigen Mitarbeiter auf die öffentlichen Seiten
        if ($isSachbearbeiterOnly) {
            $this->getLogger()->info('Welcomepage logout and redirect to home');
            $sessionHandler->logoutUser($request);

            return $this->redirectToRoute('core_home', ['status' => 'missingOrgadata']);
        }

        // verarbeite die Nutzereingaben
        if ('POST' === $request->getMethod()) {
            $requestPost = $request->request;

            // ist der User berechtigt, die Orgadaten zu verändern?
            $user = $this->currentUser->getUser();
            if ($user->getOrganisationId() != $requestPost->get('oident')) {
                $orgaId = $requestPost->get('orgaIdent');
                $this->getLogger()->warning("Ein User hat versucht, unberechtigt auf Orga {$orgaId} zuzugreifen", [$user]);

                throw new AccessDeniedException('Zugriff nicht gestattet');
            }

            // Update Orga
            $userHandler->updateOrga($requestPost->get('oident'), $requestPost->all());
            $this->getLogger()->info('Welcomepage Orga updated');

            // Update User
            $data = [
                'email'                               => $user->getEmail(), // Pflichtfeld beim Update
                'firstname'                           => $user->getFirstname(), // Pflichtfeld beim Update
                'lastname'                            => $user->getLastname(), // Pflichtfeld beim Update
                UserFlagKey::IS_NEW_USER->value       => false,
                UserFlagKey::PROFILE_COMPLETED->value => true,
                UserFlagKey::ACCESS_CONFIRMED->value  => true,
            ];

            if ($requestPost->has('newsletter')) {
                $data[UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value] = 'on';
            }

            $user = $userHandler->updateUser($user->getId(), $data);
            $this->getLogger()->info('Welcomepage user updated');

            // Sende eine Benachrichtigung, wenn der Newsletter gewünscht ist
            if ($requestPost->has('newsletter')) {
                $this->notifyNewsletterStatusChanged(
                    $mailService,
                    [
                        'firstname'    => $user->getFirstname(),
                        'lastname'     => $user->getLastname(),
                        'email'        => $user->getEmail(),
                        'gender'       => $user->getGender(),
                        'action'       => 'bestellen',
                        'organisation' => $user->getOrga()->getName(),
                    ]
                );
            }

            $this->getMessageBag()->add('confirm', 'confirm.user.saved');

            return $this->redirectToRoute('core_home_loggedin');
        }

        // handelt es sich bei dem Nutzer um einen neuen User?

        $templateVars['orga'] = $orga;
        $templateVars['user'] = $this->currentUser->getUser();
        $title = 'project.name';

        $this->getLogger()->info('Welcomepage display page');

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/gateway_newUser.html.twig',
            ['templateVars' => $templateVars, 'title' => $title]
        );
    }

    /**
     * Ueberpruefung der Eingaben im Profilformular.
     *
     * @throws SessionUnavailableException
     */
    protected function checkProfileCompleted(): array
    {
        $templateVars = [];
        if (!($this->currentUser->getUser() instanceof User)) {
            throw new SessionUnavailableException('Session korrupt');
        }
        $templateVars['profileCompleted'] = filter_var($this->currentUser->getUser()->isProfileCompleted(), FILTER_VALIDATE_BOOLEAN);
        $templateVars['newUser'] = filter_var($this->currentUser->getUser()->isNewUser(), FILTER_VALIDATE_BOOLEAN);
        $this->getLogger()->info('Check Profile completed: '.DemosPlanTools::varExport($templateVars['profileCompleted'], true)
            .' NewUser: '.DemosPlanTools::varExport($templateVars['newUser'], true));

        return $templateVars;
    }

    /**
     * Liste der Änderungen InvitableInstitution-Liste.
     *
     * @DplanPermissions("area_report_invitable_institutionlistchanges")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_orga_toeblist_changes', path: '/organisations/visibilitylog')]
    public function showInvitableInstitutionVisibilityChangesAction(UserService $userService)
    {
        $templateVars = [];
        $templateVars['reportEntries'] = $userService->getInvitableInstitutionShowlistChanges();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/toeb_showlist_changes.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.list.visibility.changes.invitable_institution',
            ]
        );
    }

    /**
     * @DplanPermissions("feature_plain_language")
     *
     * @return RedirectResponse
     */
    #[Route(name: 'DemosPlan_switch_language', path: '/language')]
    public function switchLanguageAction(EventDispatcherPostInterface $eventDispatcherPost, Request $request)
    {
        // change url:
        $event = new LanguageSwitchRequestEvent($request);
        try {
            $eventDispatcherPost->post($event);
            $request = $event->getRequest();
        } catch (Exception $e) {
            $this->logger->warning('Could not successfully process LanguageSwitchEvent ', [$e]);
        }

        // invert current locale set by setting current session:
        $languageKey = ('de' === $request->getSession()->get('_locale', 'de')) ? 'de_plain' : 'de';
        $request->getSession()->set('_locale', $languageKey);

        // redirect to current page:
        return $this->redirectBack($request);
    }

    /**
     * Portalseite des Nutzers.
     *
     * @DplanPermissions("area_portal_user")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_portal', path: '/portal/user')]
    public function portalUserAction(
        CurrentUserService $currentUser,
        ContentService $contentService,
        Request $request,
        UserHandler $userHandler,
        string $title = 'user.profile',
    ) {
        $templateVars = [];
        $userId = $currentUser->getUser()->getId();
        $user = $userHandler->getSingleUser($userId);
        $templateVars['user'] = $user;

        // get User settings
        $templateVars['emailNotificationReleasedStatement'] = false;
        // by default coordinator gets mails, if not explicitly denied
        if ($user->hasRole(Role::PUBLIC_AGENCY_COORDINATION)) {
            $templateVars['emailNotificationReleasedStatement'] = true;
        }

        $settings = $contentService->getSettings(
            'emailNotificationReleasedStatement',
            SettingsFilter::whereUser($user)->lock(),
            false
        );

        if (is_array($settings) && 1 === count($settings)) {
            $templateVars['emailNotificationReleasedStatement'] = $settings[0]->getContentBool();
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/portal_user.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $title,
            ]
        );
    }

    /**
     * @DplanPermissions("area_manage_users")
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_user_add', path: '/user/add')]
    public function addUserAction(Request $request, UserHandler $userHandler): RedirectResponse
    {
        try {
            if ($request->isMethod('POST')) {
                $user = $userHandler->addUser($request->request);

                if ($user instanceof User) {
                    $this->getMessageBag()->add('confirm', 'confirm.user.created');
                }
            }
        } catch (EmailAddressInUseException|LoginNameInUseException) {
            $this->getMessageBag()->add('error', 'error.login.or.email.not.unique');
            $this->getMessageBag()->add('error', 'error.user.login.exists');
        } catch (UserAlreadyExistsException) {
            $this->getMessageBag()->add('error', 'error.user.login.exists');
        } catch (Exception) {
            $this->getLogger()->error('New User Entity could not been saved');
            $this->getMessageBag()->add('error', 'error.save');
        }

        return $this->redirectToRoute('DemosPlan_user_list');
    }

    /**
     * @DplanPermissions("feature_citizen_registration")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_citizen_register', path: '/user/register', methods: ['POST'], options: ['expose' => true])]
    public function registerCitizenAction(
        CsrfTokenManagerInterface $csrfTokenManager,
        EventDispatcherPostInterface $eventDispatcherPost,
        RateLimiterFactory $userRegisterLimiter,
        Request $request,
        TranslatorInterface $translator,
        UserHandler $userHandler,
    ) {
        try {
            // check Honeypotfields

            $event = new RequestValidationWeakEvent($request);
            try {
                $eventDispatcherPost->post($event);
            } catch (Exception $e) {
                $this->logger->warning('Could not successfully verify registration citizen form ', [$e]);
                $this->getMessageBag()->add('error', 'user.registration.fail');

                return $this->redirectToRoute('DemosPlan_citizen_register');
            }

            // avoid brute force attacks
            $limiter = $userRegisterLimiter->create($request->getClientIp());
            if (false === $limiter->consume()->isAccepted()) {
                $this->messageBag->add('warning', 'warning.user.register.throttle');

                return $this->redirectToRoute('core_home');
            }

            $submittedToken = $request->request->get('_csrf_token');
            $tokenId = 'register-user';
            if (!$this->isCsrfTokenValid($tokenId, $submittedToken)) {
                $this->logger->warning('User entered invalid csrf token on user registration', [$submittedToken]);
                $this->getMessageBag()->add('error', 'user.registration.invalid.csrf');

                return $this->redirectToRoute('DemosPlan_citizen_register');
            }

            // explicitly remove token, so it can not be used again, as tokens
            // are by design valid as long as the session exists to avoid problems
            // in xhr requests. We do not need this here, instead, we need to
            // make sure that the token is only valid once.
            $csrfTokenManager->refreshToken($tokenId);

            $user = $userHandler->createCitizen($request->request);

            try {
                $userHandler->inviteUser($user);
                $this->getMessageBag()->add('confirm', 'confirm.email.registration.sent');
            } catch (SendMailException) {
                $this->getMessageBag()->add('error', 'error.email.invitation.send.to.user');
            }

            return $this->redirectToRoute('core_home');
            // as the email-address is used as login name the handling for both exceptions is the same
        } catch (UserAlreadyExistsException $e) {
            $emailAddress = $e->getValue();
            $this->getMessageBag()->addObject(LinkMessageSerializable::createLinkMessage(
                'warning',
                'warning.user.emailaddress.in_use',
                ['emailAddress' => $emailAddress],
                'DemosPlan_user_login_alternative',
                [],
                $translator->trans('login.page.quicklink')
            ));
            $this->logger->warning('Registration failed, email address in use', ['emailAddress' => $emailAddress]);
        } catch (ViolationsException $e) {
            $violations = $e->getViolations();
            if (null !== $violations) {
                /** @var ConstraintViolationInterface $violation */
                foreach ($violations as $violation) {
                    $this->getMessageBag()->add('error', $violation->getMessage());
                }
            } else {
                $this->getMessageBag()->add('error', 'user.registration.fail');
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Registration failed due to unexpected exception', [$e]);
            $this->getMessageBag()->add('error', 'user.registration.fail');
        }

        return $this->redirectToRoute('DemosPlan_citizen_registration_form');
    }

    /**
     * @DplanPermissions("feature_citizen_registration")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_citizen_registration_form', path: '/user/register', methods: ['GET'], options: ['expose' => true])]
    public function showRegisterCitizenFormAction()
    {
        $title = 'user.register';

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/citizen_register_form.html.twig',
            ['title' => $title, 'useIdp' => false]
        );
    }

    /**
     * Speichere Nutzerdaten.
     *
     * @DplanPermissions("area_portal_user")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_user_edit', path: '/user/edit')]
    public function editUserAction(CurrentUserService $currentUser, ContentService $contentService, MailService $mailService, Request $request, UserHandler $userHandler)
    {
        try {
            $userBefore = $currentUser->getUser();
            // Store status before update to compare it to the status after the update
            $newsletterStatusBefore = $userBefore->getNewsletter();

            $user = $userHandler->updateUser($userBefore->getId(), $request->request->all());
            if ($user instanceof User) {
                // soll eine Benachrichtigung verschickt werden, dass sich der Newsletterstatus verändert hat?
                if ($newsletterStatusBefore !== $user->getNewsletter()) {
                    $this->notifyNewsletterStatusChanged(
                        $mailService,
                        [
                            'firstname'    => $user->getFirstname(),
                            'lastname'     => $user->getLastname(),
                            'email'        => $user->getEmail(),
                            'gender'       => $user->getGender(),
                            'action'       => $user->getNewsletter() ? 'bestellen' : 'abbestellen',
                            'organisation' => $user->getOrga()->getName(),
                        ]
                    );
                }

                $this->getMessageBag()->add('confirm', 'confirm.user.saved');
            }

            $data = [
                'userId'  => is_array($user) ? $user['id'] : $user->getId(),
                'content' => $request->request->has('emailNotificationReleasedStatement'),
            ];

            $contentService->setSetting('emailNotificationReleasedStatement', $data);
        } catch (Exception) {
            $this->getMessageBag()->add('error', 'error.save');
        }

        return $this->redirectToRoute('DemosPlan_user_portal');
    }

    /**
     * Create a new AddressBookEntry for the given Organisation.
     * Included email-address will be validated.
     *
     * @DplanPermissions("area_admin_orga_address_book")
     *
     * @param string $organisationId
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_create_addresses_entry', path: '/organisation/adressen/erstellen/{organisationId}', methods: ['POST'])]
    public function createAddressBookEntryAction(
        AddressBookEntryService $addressBookEntryService,
        OrgaService $orgaService,
        Request $request,
        ValidatorInterface $validator,
        $organisationId)
    {
        $checkResult = $this->checkUserOrganisation($organisationId, 'DemosPlan_get_address_book_entries');
        if ($request instanceof RedirectResponse) {
            return $checkResult;
        }

        $requestPost = $request->request;

        if (!$requestPost->has('r_emailAddress')) {
            $this->getMessageBag()->add('error', 'error.missing.emailAddress');

            return $this->redirectToRoute(
                'DemosPlan_get_address_book_entries',
                ['organisationId' => $organisationId]
            );
        }

        $organisation = $orgaService->getOrga($organisationId);
        $name = $requestPost->has('r_name') ? $requestPost->get('r_name') : null;
        $emailAddress = $requestPost->get('r_emailAddress');

        $addressBookEntryVO = new AddressBookEntryVO($name, $emailAddress, $organisation);

        $violations = $validator->validate($addressBookEntryVO);
        foreach ($violations as $violation) {
            $this->getMessageBag()->add('warning', $violation->getMessage());
        }

        $addressBookEntry = null;
        // no violation? persist new entry
        if (0 === $violations->count()) {
            try {
                $addressBookEntry = $addressBookEntryService->createAddressBookEntry($addressBookEntryVO);
            } catch (Exception $e) {
                $this->getLogger()->error('Error on createAddressBookEntryAction(): ', [$e]);
                $this->getMessageBag()->add('warning', 'warning.addressBookEntry.not.created');
            }
        }

        if ($addressBookEntry instanceof AddressBookEntry) {
            $this->getMessageBag()->add('confirm', 'confirm.addressBookEntry.created');
        }

        return $this->redirectToRoute(
            'DemosPlan_get_address_book_entries',
            ['organisationId' => $organisationId]
        );
    }

    /**
     * Deletes a s by IDs.
     * Incoming organisationId is required, to verify action.
     *
     * @DplanPermissions("area_admin_orga_address_book")
     *
     * @param string $organisationId
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_delete_email_addresses_entry', path: '/organisation/adressen/loeschen/{organisationId}', methods: ['POST'])]
    public function deleteAddressBookEntriesAction(AddressBookEntryService $addressBookEntryService, Request $request, $organisationId)
    {
        $checkResult = $this->checkUserOrganisation($organisationId, 'DemosPlan_get_address_book_entries');
        if ($request instanceof RedirectResponse) {
            return $checkResult;
        }

        $requestPost = $request->request;
        if (!$requestPost->has('entry_selected')) {
            $this->getMessageBag()->add('warning', 'warning.select.entries');

            return $this->redirectToRoute(
                'DemosPlan_get_address_book_entries',
                ['organisationId' => $organisationId]
            );
        }

        $addressBookEntryIds = $requestPost->get('entry_selected');

        try {
            $addressBookEntryService->deleteAddressBookEntries($addressBookEntryIds);
            $this->getMessageBag()->add('confirm', 'confirm.addressBookEntry.deleted');
        } catch (Exception) {
            // while loop over addressbookentries, exception was thrown
            $this->getMessageBag()->add('warning', 'warning.addressBookEntries.not.deleted');
        }

        return $this->redirectToRoute(
            'DemosPlan_get_address_book_entries',
            ['organisationId' => $organisationId]
        );
    }

    /**
     *  @DplanPermissions({"area_portal_user","feature_statement_gdpr_consent"})
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_user_statements', path: '/portal/user/statements', options: ['expose' => true])]
    public function statementListAction(CurrentUserService $currentUser, StatementService $statementService)
    {
        $templateVars = [];
        $user = $currentUser->getUser();
        $userId = $user->getId();

        $templateVars['user'] = $user;

        $templateVars['statements'] = $statementService->getSubmittedOrAuthoredStatements($userId);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/list_users_statements.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.statements',
            ]
        );
    }

    /**
     *  @DplanPermissions({"area_portal_user","feature_statement_gdpr_consent_may_revoke"})
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_revoke_statement', path: '/portal/user/statement/{statementId}/revoke')]
    public function revokeGDPRConsentForStatementAction(
        CurrentUserService $currentUser,
        StatementAnonymizeService $statementAnonymizeService,
        StatementService $statementService,
        Request $request,
        string $statementId,
    ) {
        $statement = null;
        try {
            $userId = $currentUser->getUser()->getId();
            $statement = $statementService->getStatement($statementId);

            if (!$statement instanceof Statement) {
                throw new EntityIdNotFoundException('Statement was not found.');
            }

            if (!$statement->isSubmitter($userId) && !$statement->isAuthor($userId)) {
                throw new AccessDeniedException('The given user is not permitted to anonymize the given Statement, becauses he is neither the submitter nor the author.');
            }

            $updatedStatement = $statementAnonymizeService->anonymizeUserDataOfStatement(
                $statement,
                true,
                true,
                $userId,
                true
            );

            if ($updatedStatement instanceof Statement) {
                if ($updatedStatement->isConsentRevoked()) {
                    $this->getMessageBag()->add('confirm', 'confirm.statement.anonymized', ['externId' => $statement->getExternId()]);
                } else {
                    $this->getMessageBag()->add('warning', 'warning.statement.could.not.be.anonymized', ['externId' => $statement->getExternId()]);
                }
            } else {
                $this->getMessageBag()->add('warning', 'error.statement.anonymized', ['externId' => $statement->getExternId()]);
            }
        } catch (AccessDeniedException) {
            $this->getMessageBag()->add('error', 'error.gdpr.revoke.of.statement.not.permitted');
        } catch (EntityIdNotFoundException) {
            $this->getMessageBag()->add('error', 'error.statement.not.found');
        } catch (Exception $e) {
            $externId = $statement instanceof Statement ? $statement->getExternId() : '';
            $this->getMessageBag()->add('error', 'error.statement.anonymize', ['externId' => $externId]);

            return $this->handleError($e);
        }

        return $this->redirectToRoute('DemosPlan_user_statements', ['request' => $request]);
    }

    /**
     * Mail an Newsletterempfänger bei Statusänderung.
     *
     * @param array $data
     *
     * @throws Exception
     */
    protected function notifyNewsletterStatusChanged(MailService $mailService, $data): void
    {
        $to = $this->parameterBag->get('newsletter_recipient');
        $from = '';
        try {
            $mailService->sendMail(
                'notify_newsletter_changes',
                'de_DE',
                $to,
                $from,
                '',
                '',
                'extern',
                [
                    'firstname'    => $data['firstname'],
                    'lastname'     => $data['lastname'],
                    'organisation' => $data['organisation'],
                    'email'        => $data['email'],
                    'gender'       => $data['gender'],
                    'action'       => $data['action'],
                ]
            );
            $this->getLogger()->info('Newsletter statusChangedMail sent');
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not send NewsletterStatus mail: ', [$e]);
            throw new Exception('Could not send NewsletterStatus mail', $e->getCode(), $e);
        }
    }

    /**
     * Cover case of URL manipulation to access/manipulate address book of foreign organisation.
     *
     * @param string $assertedOrganisationId
     * @param string $redirectRoute
     *
     * @return RedirectResponse|void
     */
    protected function checkUserOrganisation($assertedOrganisationId, $redirectRoute)
    {
        $currentUser = $this->getUser();
        if (($currentUser instanceof User) && $currentUser->getOrganisationId() !== $assertedOrganisationId) {
            return $this->redirectToRoute($redirectRoute, ['organisationId' => $currentUser->getOrganisationId()]);
        }
    }
}
