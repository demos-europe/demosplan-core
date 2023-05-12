<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Hslavich\OneloginSamlBundle\Security\Http\Authentication\SamlAuthenticationSuccessHandler as ParentSamlAuthenticationSuccessHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SamlAuthenticationSuccessHandler extends ParentSamlAuthenticationSuccessHandler
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $this->logger->info('User was logged in from SAML', ['id' => $user->getId(), 'roles' => implode(',', $user->getRoleCodes())]);

        // propagate user login to session
        $request->getSession()->set('userId', $user->getId());

        return parent::onAuthenticationSuccess($request, $token);
    }
}
