<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRevokeTokenAlreadyUsedException;
use demosplan\DemosPlanCoreBundle\Exception\GdprConsentRevokeTokenNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidPostDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\GdprConsentRevokeTokenService;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GdprConsentRevokeTokenController extends BaseController
{
    private const POST_PARAM_KEY_EMAIL_ADDRESS = 'emailAddress';
    private const POST_PARAM_KEY_GDPR_CONSENT_REVOKE_TOKEN = 'gdprConsentRevokeToken';

    /**
     *
     * @DplanPermissions("area_gdpr_consent_revoke_page")
     * @throws MessageBagException
     */
    #[Route(path: '/einwilligung-widerrufen', methods: ['POST'], name: 'DemosPlan_statement_revoke_gdpr_consent_post')]
    public function revokeGdprConsentPostAction(GdprConsentRevokeTokenService $gdprConsentRevokeTokenService, Request $request): Response
    {
        try {
            $messageBag = $this->getMessageBag();
            try {
                $emailAddress = $this->getStringParameter($request, self::POST_PARAM_KEY_EMAIL_ADDRESS);
                $tokenValue = $this->getStringParameter($request, self::POST_PARAM_KEY_GDPR_CONSENT_REVOKE_TOKEN);
                assert(2 === $request->request->count());
                $gdprConsentRevokeTokenService->revokeConsentByTokenIdAndEmailAddress($tokenValue, $emailAddress);
                $messageBag->add('confirm', 'gdpr.revoke.token.request.success');
            } catch (InvalidPostDataException $e) {
                $this->getMessageBag()->add('error', 'gdpr.revoke.token.request.invalid');
            } catch (GdprConsentRevokeTokenAlreadyUsedException $e) {
                $messageBag->add('error', 'gdpr.revoke.token.already_used');
            } catch (GdprConsentRevokeTokenNotFoundException $e) {
                $messageBag->add('error', 'gdpr.revoke.token.request.mismatch');
            }

            return $this->redirectToRoute('DemosPlan_statement_revoke_gdpr_consent_get');
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     *
     * @throws Exception
     */
    #[Route(path: '/einwilligung-widerrufen', methods: ['GET'], name: 'DemosPlan_statement_revoke_gdpr_consent_get')]
    public function revokeGdprConsentGetAction(PermissionsInterface $permissions): Response
    {
        if ($permissions->hasPermission('area_gdpr_consent_revoke_page')) {
            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanCore/gdpr_consent_revoke.html.twig',
                ['title' => 'gdpr.consent.revoke']
            );
        }

        if ($permissions->hasPermission('area_gdpr_consent_revoke_page_disabled')) {
            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanCore/gdpr_consent_revoke_disabled.html.twig',
                ['title' => 'gdpr.consent.revoke.disabled']
            );
        }

        throw AccessDeniedException::missingPermission('area_gdpr_consent_revoke_page');
    }
}
