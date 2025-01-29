<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\Event\User\NewOrgaRegisteredEvent;
use demosplan\DemosPlanCoreBundle\Event\User\OrgaEditedEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\EmailAddressInUseException;
use demosplan\DemosPlanCoreBundle\Exception\LoginNameInUseException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator\OsiHHAuthenticator;
use Exception;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class DemosPlanOrgaController extends BaseController
{
    public function __construct(private readonly OrgaHandler $orgaHandler, private readonly UserHandler $userHandler)
    {
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_verify_orga_switch_or_update', path: '/organisation/verifychanges')]
    public function verifyOrgaSwitchOrUpdateAction(AuthenticationUtils $authenticationUtils, Request $request)
    {
        $session = $request->getSession();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/verify_orga_switch_or_update.html.twig',
            [
                'templateVars' => [
                    'type'        => 'Organisation',
                    'currentName' => $session->get('unknownChange_userOrgaName'),
                    'gatewayName' => $session->get('unknownChange_gatewayOrgaName'),
                    'lastUsername'=> $authenticationUtils->getLastUsername(),
                ],
            ]
        );
    }

    // @improve T15377

    /**
     * Administrate organisations.
     * In this case administrate means, save or delete organisations.
     *
     * @DplanPermissions("area_manage_orgas")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    public function adminOrgasAction(Request $request)
    {
        // wenn der request gefüllt ist, bearbeite ihn
        if (0 < $request->request->count()) {
            $requestPost = $request->request;
            $orgaIdent = $this->userHandler->adminOrgasHandler($requestPost);

            // check wether there was an error
            if (is_string($orgaIdent) && 36 === strlen($orgaIdent)) {
                return $this->redirect($this->generateUrl('DemosPlan_orga_list')."#{$orgaIdent}");
            }
        }

        return $this->redirectToRoute('DemosPlan_orga_list');
    }

    /**
     * @DplanPermissions("area_manage_orgadata")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_orga_edit_view', path: '/organisation/edit/{orgaId}', methods: ['GET'])]
    public function editOrgaViewAction(CurrentUserService $currentUser, OrgaTypeRepository $orgaTypeRepository, string $orgaId)
    {
        $accessPreventionRedirect = $this->preventInvalidOrgaAccess($orgaId, $currentUser->getUser());
        if (null !== $accessPreventionRedirect) {
            return $accessPreventionRedirect;
        }

        $templateVars = $this->getEditOrgaTemplateVars($orgaTypeRepository, $orgaId);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/edit_orga.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.edit.orga',
            ]
        );
    }

    /**
     * Edit Organisation.
     *
     * @DplanPermissions("area_manage_orgadata")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_orga_edit_save', path: '/organisation/edit/{orgaId}', methods: ['POST'], options: ['expose' => true])]
    public function editOrgaSaveAction(
        CurrentUserService $currentUser,
        EventDispatcherPostInterface $eventDispatcherPost,
        Request $request,
        UserHandler $userHandler,
        OrgaHandler $orgaHandler,
        string $orgaId,
    ) {
        $requestPost = $request->request;
        $accessPreventionRedirect = $this->preventInvalidOrgaAccess($orgaId, $currentUser->getUser());
        if (null !== $accessPreventionRedirect) {
            return $accessPreventionRedirect;
        }

        if (0 < $requestPost->count()) {
            $user = $currentUser->getUser();
            $data = $this->handleRequestForSingleOrga($requestPost);
            if (isset($data['ident'])) {
                if ($data['ident'] !== $orgaId) {
                    $this->getLogger()->warning(
                        "User {$user->getId()} der Orga {$user->getOrganisationId()} hat versucht die Orga {$data['ident']} zu editieren"
                    );
                    $this->getMessageBag()->add('error', 'warning.access.denied');

                    return $this->redirectToRoute('core_home_loggedin');
                }
                $orgaBefore = clone $orgaHandler->getOrga($orgaId);
                $updatedOrganisation = $userHandler->updateOrga($orgaId, $data);

                if ($orgaBefore instanceof Orga && $updatedOrganisation instanceof Orga) {
                    // fire event
                    $event = new OrgaEditedEvent(
                        $orgaBefore,
                        $updatedOrganisation
                    );

                    $eventDispatcherPost->post($event);
                    $this->getMessageBag()->add('confirm', 'confirm.all.changes.saved');
                }
            }
        }

        // Lade die Seite neu, damit das Formular nicht erneut abgeschickt werden kann
        return $this->redirectToRoute('DemosPlan_orga_edit_view', ['orgaId' => $orgaId]);
    }

    /**
     * Edit Organisation design (logo).
     *
     *  @DplanPermissions({"area_manage_orgadata","feature_orga_logo_edit"})
     *
     * @param string $orgaId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_orga_branding_edit', path: '/organisation/branding/edit/{orgaId}', options: ['expose' => true])]
    public function editOrgaBrandingAction(Request $request, FileUploadService $fileUploadService, OrgaTypeRepository $orgaTypeRepository, $orgaId)
    {
        $requestPost = $request->request;

        if (0 < (is_countable($requestPost) ? count($requestPost) : 0)) { // always true
            $deleteLogo = ($requestPost->has('r_logoDelete') && 'deleteLogo' === $requestPost->get('r_logoDelete'));
            $data = $this->handleRequestForSingleOrga($requestPost);
            $data['logo'] = $fileUploadService->prepareFilesUpload($request, 'r_orgaLogo');

            if (isset($data['logo'])
                && ('' !== $data['logo'] || ('' === $data['logo'] && $deleteLogo))
            ) {
                $updatedOrganisation = $this->userHandler->updateOrga($orgaId, $data);
                if ($updatedOrganisation instanceof Orga) {
                    if ('' === $data['logo']) {
                        $this->getMessageBag()->add('confirm', 'confirm.file.deleted');
                    } else {
                        $this->getMessageBag()->add('confirm', 'confirm.file.uploaded');
                    }
                }
            }

            // reload of page to prevent repeated form submission
            return $this->redirectToRoute('DemosPlan_orga_branding_edit', ['orgaId' => $orgaId]);
        }

        $templateVars = $this->getEditOrgaTemplateVars($orgaTypeRepository, $orgaId);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/edit_orga_branding.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.edit.orga.branding',
            ]
        );
    }

    /**
     * @throws MessageBagException
     */
    protected function preventInvalidOrgaAccess(string $orgaId, User $user): ?RedirectResponse
    {
        if ($orgaId !== $user->getOrganisationId()) {
            $this->getLogger()->warning(
                'User '.$user->getId().' of organization '.
                $user->getOrganisationId().
                ' tried to access details of organization '.$orgaId
            );
            $this->getMessageBag()->add('error', 'warning.access.denied');

            return $this->redirectToRoute('core_home_loggedin');
        }

        return null;
    }

    protected function handleRequestForSingleOrga(ParameterBag $requestPost): array
    {
        $orga = [];
        $data = [];

        $result = $this->transformOrgaRequestVariables($requestPost->all());

        if (is_array($result) && isset($result['submittedOrgas']) && 1 === (is_countable($result['submittedOrgas']) ? count($result['submittedOrgas']) : 0)) {
            $orga['ident'] = $result['submittedOrgas'][0];
            foreach ($result['submittedKeys'] as $key) {
                $orga[$key] = $requestPost->get($result['submittedOrgas'][0].':'.$key);
            }
            $data = $orga;
        } elseif (isset($result['organisation_ident']) && isset($result[$result['organisation_ident']])) {
            $data['ident'] = $result['organisation_ident'];
            $data = array_merge($data, $result[$data['ident']]);
        }

        // mark data as submitted via form
        $data['isFormPost'] = true;

        return $data;
    }

    /**
     * Mapping of incoming Variables from $requestPost to array with submitted organisatoins and subbmitted keys.
     */
    protected function transformOrgaRequestVariables(array $requestPost): array
    {
        $result = [];
        $submittedOrgas = [];
        $submittedKeys = [];

        foreach (array_keys($requestPost) as $key) {
            $keyParts = explode(':', $key);
            if (2 === count($keyParts)) {
                $submittedOrgas[] = $keyParts[0];
                $submittedKeys[] = $keyParts[1];
            }
        }
        $result['submittedOrgas'] = array_unique($submittedOrgas);
        $result['submittedKeys'] = array_unique($submittedKeys);

        return $result;
    }

    /**
     * List of organisations to administrate.
     *
     * @DplanPermissions("area_organisations")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_orga_list', path: '/organisation/list')]
    public function listOrgasAction(): RedirectResponse|Response
    {
        $templateVars = [];
        $templateVars['proceduresDirectlinkPrefix'] = $this->generateUrl(
            'DemosPlan_procedure_public_orga_index',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $templateVars['writableOrgaFields'] = array_keys($this->orgaHandler->getWritableAttributes());
        $templateVars['availableOrgaTypes'] = $this->getFormParameter('orga_types');

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/list_orgas.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.admin.orgas',
            ]
        );
    }

    /**
     * Wechsle die Organisation eines Users.
     *
     * @DplanPermissions("feature_switchorga")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_user_switch_orga', path: '/organisation/switch')]
    public function switchOrgaAction(
        CurrentUserInterface $currentUser,
        OsiHHAuthenticator $osiHHAuthenticator,
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
    ): Response {
        // Wenn es zwei Organisationen zu dem User gibt, tausche die aktive Session aus
        if ($currentUser->getUser()->hasTwinUser()) {
            $this->logger->info('Switch user Orga');

            return $userAuthenticator->authenticateUser(
                $currentUser->getUser()->getTwinUser(),          // the User object you just created
                $osiHHAuthenticator,
                $request,
            );
        }

        $this->getLogger()->warning('Tried to switch orga but no user twin found');
        $this->getMessageBag()->add('error', 'error.organisation.not.switched');

        $redirectTo = $this->generateUrl('core_home_loggedin');
        if ($request->headers->has('referer')) {
            $redirectTo = $request->headers->get('referer');
        }

        return $this->redirect($redirectTo);
    }

    /**
     * Generate templateVars for similar edit Orga actions.
     *
     * @param string $orgaId
     *
     * @throws Exception
     */
    protected function getEditOrgaTemplateVars(OrgaTypeRepository $orgaTypeRepository, $orgaId): array
    {
        $templateVars = [];
        $orga = $this->orgaHandler->getOrga($orgaId);
        $templateVars['orga'] = $orga;
        $templateVars['submissionTypeDefault'] = Orga::STATEMENT_SUBMISSION_TYPE_DEFAULT;
        $templateVars['submissionTypeShort'] = Orga::STATEMENT_SUBMISSION_TYPE_SHORT;

        // Add OrgaTypes to frontend. Needed in create orga form.
        $templateVars['orgaTypes'] = [];
        /** @var OrgaType $type */
        foreach ($orgaTypeRepository->findAll() as $type) {
            $templateVars['orgaTypes'][] = [
                'id'    => $type->getId(),
                'label' => $type->getLabel(),
                'name'  => $type->getName(),
            ];
        }
        $templateVars['proceduresDirectlinkPrefix'] = $this->generateUrl(
            'DemosPlan_procedure_public_orga_index',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $templateVars;
    }

    /**
     *  @DplanPermissions("feature_orga_registration")
     *
     * @throws CustomerNotFoundException
     */
    #[Route(name: 'DemosPlan_orga_register_form', path: '/organisation/register', methods: ['GET'], options: ['expose' => true])]
    public function editOrgaRegisterAction(CustomerHandler $customerHandler): Response
    {
        $customer = $customerHandler->getCurrentCustomer();

        $templateVars = [];
        $templateVars['customerName'] = $customer->getName();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanUser/orga_register_form.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'user.register',
            ]
        );
    }

    /**
     * @DplanPermissions("feature_orga_registration")
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_orga_register', path: '/organisation/register', methods: ['POST'], options: ['expose' => true])]
    public function createOrgaRegisterAction(
        CsrfTokenManagerInterface $csrfTokenManager,
        EventDispatcherPostInterface $eventDispatcherPost,
        RateLimiterFactory $userRegisterLimiter,
        Request $request,
        OrgaService $orgaService,
        CustomerHandler $customerHandler,
    ): RedirectResponse {
        try {
            // check Honeypotfields
            $event = new RequestValidationWeakEvent($request);
            try {
                $eventDispatcherPost->post($event);
            } catch (Exception $e) {
                $this->logger->warning('Could not successfully verify orga register form ', [$e]);
                $this->getMessageBag()->add('error', 'user.registration.fail');

                return $this->redirectToRoute('DemosPlan_orga_register');
            }

            $submittedToken = $request->request->get('_csrf_token');
            $tokenId = 'register-orga';
            if (!$this->isCsrfTokenValid($tokenId, $submittedToken)) {
                $this->logger->warning('User entered invalid csrf token on orga registration', [$submittedToken]);
                $this->getMessageBag()->add('error', 'user.registration.invalid.csrf');

                return $this->redirectToRoute('DemosPlan_orga_register');
            }

            // explicitly remove token, so it can not be used again, as tokens
            // are by design valid as long as the session exists to avoid problems
            // in xhr requests. We do not need this here, instead, we need to
            // make sure that the token is only valid once.
            $csrfTokenManager->refreshToken($tokenId);

            // avoid brute force attacks
            $limiter = $userRegisterLimiter->create($request->getClientIp());
            if (false === $limiter->consume()->isAccepted()) {
                $this->messageBag->add('warning', 'warning.user.register.throttle');

                return $this->redirectToRoute('core_home');
            }

            $customer = $customerHandler->getCurrentCustomer();
            $customerName = $customer->getName();

            $orgaName = $request->request->get('r_organame');
            $userFirstName = $request->request->get('r_firstname');
            $userLastName = $request->request->get('r_lastname');
            $userEmail = $request->request->get('r_useremail');
            $phone = $request->request->get('r_orgaphone');
            $orgaTypeNames = $request->request->get('r_orgatype') ?? [OrgaType::PUBLIC_AGENCY];

            $orgaService->createOrgaRegister($orgaName, $phone, $userFirstName, $userLastName, $userEmail, $customer, $orgaTypeNames);

            try {
                $newOrgaRegisteredEvent = new NewOrgaRegisteredEvent(
                    $userEmail,
                    $orgaTypeNames,
                    $customerName,
                    $userFirstName,
                    $userLastName,
                    $orgaName
                );
                $eventDispatcherPost->post($newOrgaRegisteredEvent);
            } catch (Exception $e) {
                $this->logger->warning('Could not successfully perform orga registered event', [$e]);
            }

            $this->getMessageBag()->add('confirm', 'confirm.orga.register.request');
        } catch (EmailAddressInUseException|LoginNameInUseException $e) {
            $this->getMessageBag()->add('error', 'error.login.or.email.not.unique');
            $this->logger->error($e->getMessage());
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.orga.register.request');
            $this->logger->error($e->getMessage());
        }

        return $this->redirectToRoute('DemosPlan_orga_register_form');
    }
}
