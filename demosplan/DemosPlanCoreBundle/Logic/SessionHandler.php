<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Application\Header;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedGuestException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\SessionUnavailableException;

class SessionHandler extends PdoSessionHandler
{
    /** @var Request */
    protected $request;

    /** @var Logger */
    protected $logger;

    /** @var Permissions */
    protected $permissions;

    /** @var ProcedureService */
    protected $procedureService;

    /** @var CurrentProcedureService */
    private $currentProcedureService;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * Constructor SessionHandler.
     */
    public function __construct(
        CurrentProcedureService $currentProcedureService,
        CurrentUserInterface $currentUser,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage
    ) {
        // configure PdoSessionHandler
        $dsn = 'mysql:host='.$parameterBag->get('database_host').';dbname='.$parameterBag->get('database_name');
        $pdoOptions = [
            'db_username'  => $parameterBag->get('database_user'),
            'db_password'  => $parameterBag->get('database_password'),
        ];
        parent::__construct($dsn, $pdoOptions);

        $this->currentProcedureService = $currentProcedureService;
        $this->currentUser = $currentUser;
        $this->logger = $logger;
        $this->permissions = $permissions;
        $this->procedureService = $procedureService;
        $this->request = $requestStack->getCurrentRequest();
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Initialisiere die Session.
     *
     * @throws Exception
     */
    public function initialize(array $context = null): void
    {
        try {
            $user = $this->currentUser->getUser();

            // Die Berechtigungen und das Menühighlighting initialisieren und in der Session ablegen.

            if (null === $user) {
                $token = $this->tokenStorage->getToken();
                if ($token instanceof TokenInterface && null !== $token->getUser()) {
                    $user = $token->getUser();
                } else {
                    // At this point, something horrible happened, let's just proceed
                    // with an anonymous user
                    $user = new AnonymousUser();
                    $this->logger->warning('Failed to retrieve user for permissions');
                }
            }

            $this->permissions->initPermissions($user, $context);

            // Lege die Procedureinformationen in die Session
            $this->handleProcedure();
            $this->permissions->checkProcedurePermission();

            // prüfe die Rechte für den Bereich
            $this->permissions->checkPermissions($context);
        } catch (AccessDeniedException $e) {
            // Wenn der User vorher keine Session hatte, ist eher die Session abgelaufen,
            // als dass es ein echtes AccessDenied ist
            if (null === $this->request->getSession()->getId()) {
                $this->logger->info('Access Denied nach nicht vorhandener Session: ', [$e]);
                throw new AccessDeniedGuestException();
            }
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Session Initialization not successful', [$e]);
            throw new SessionUnavailableException('Session Initialization not successful: '.$e);
        }
    }

    /**
     * Clear all Sessiondata of current user.
     *
     * @param Request $request
     */
    public function logoutUser($request): void
    {
        // If it's desired to kill the session, also delete the session cookie.
        // Note: This will destroy the session, and not just the session data!
        $request->getSession()->invalidate();
    }

    /**
     * @throws Exception
     */
    protected function handleProcedure(): void
    {
        // variable name in route might be procedure or procedureId
        $procedureId = $this->determineProcedureId();
        // in der Abwägungstabelle wird ident genutzt
        if (null === $procedureId) {
            $procedureId = $this->request->get('ident', null);
        }

        $this->logger->debug('Ermittelte ID des Verfahrens: '.$procedureId);

        if (!empty($procedureId)) {
            $procedure = $this->procedureService->getProcedure($procedureId);

            if ($procedure instanceof Procedure) {
                $this->currentProcedureService->setProcedure($procedure);

                // check whether user may participate in this procedure via Consultation Token
                // this is temporary and will be better be solved via an SecurityVoter
                $this->permissions->evaluateUserInvitedInProcedure($procedure, $this->request->getSession());
            }
        }

        // save current procedure
        $this->permissions->setProcedure($procedure ?? null);
    }

    /**
     * Determine the procedure ID to use in the session from the given URL or the HTTP request header.
     * If both are set they must be equal. If only one of them is set the procedure ID from the URL
     * has precedence.
     */
    protected function determineProcedureId(): ?string
    {
        // try to get it from the URL
        $urlProcedureId = $this->request->get('procedure', $this->request->get('procedureId'));
        $headerProcedureId = $this->request->headers->get(Header::PROCEDURE_ID);
        // if both are set with different value then log it
        if ((null !== $urlProcedureId && '' !== $urlProcedureId)
            && (null !== $headerProcedureId && '' !== $headerProcedureId)
            && $urlProcedureId !== $headerProcedureId) {
            $compact = compact('urlProcedureId', 'headerProcedureId');
            $this->logger->info('procedure ID mismatch', [$compact, debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)]);
        }

        // when called from outside a procedure header might be set as "undefined"
        if ('undefined' === $headerProcedureId) {
            $headerProcedureId = null;
        }

        return $urlProcedureId ?? $headerProcedureId;
    }
}
