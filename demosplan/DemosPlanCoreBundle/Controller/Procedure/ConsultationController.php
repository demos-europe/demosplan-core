<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationFloodEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\CookieException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConsultationController extends BaseController
{
    /**
     * Handle Autorisation via ConsultationTokens.
     *
     * @Route(
     *     name="core_auth_procedure_consultation",
     *     path="/consultation/auth/{procedureId}"
     * )
     *
     * @DplanPermissions("feature_public_consultation")
     */
    public function procedureConsultationAuthorizeAction(
        ConsultationTokenService $consultationTokenService,
        EventDispatcherPostInterface $eventDispatcherPost,
        Request $request,
        string $procedureId
    ): RedirectResponse {
        $response = $this->redirectToRoute('DemosPlan_procedure_public_detail', ['procedure' => $procedureId]);

        $event = new RequestValidationFloodEvent(
            $request,
            $response,
            'consultationToken',
            $procedureId
        );

        try {
            $eventDispatcherPost->post($event);
            $response = $event->getResponse();
        } catch (CookieException|Exception $e) {
            return $response;
        }

        try {
            $token = $consultationTokenService->findByTokenString($request->request->get('token'));

            if ($token->getStatement() instanceof Statement && $token->getStatement()->getProcedureId() === $procedureId) {
                $invitedProcedures = $request->getSession()->get('invitedProcedures', []);
                $invitedProcedures[] = $procedureId;
                $request->getSession()->set('invitedProcedures', array_unique($invitedProcedures));
                $this->messageBag->add('confirm', 'confirm.token.valid');
            }
        } catch (EntityNotFoundException $exception) {
            $this->logger->info('Could not verify ConsultationToken', [$exception]);
            $this->messageBag->add('warning', 'warning.token.invalid');
        }

        return $response;
    }
}
