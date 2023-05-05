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

use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperDataportGatewaySHStatic;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

final class OsiSHStaticAuthenticator extends OsiAuthenticator
{
    /**
     * @var UserMapperDataportGatewaySHStatic
     */
    protected $userMapper;

    public function supports(Request $request): bool
    {
        return in_array($request->attributes->get('_route'), self::LOGIN_ROUTES, true)
            && $request->query->has('TokenTest');
    }

    protected function getCredentials(Request $request): Credentials
    {
        $osiToken = $request->query->get('TokenTest');
        $request->getSession()->set(Security::LAST_USERNAME, $osiToken);
        $credentialsVO = new Credentials();
        $credentialsVO->setToken($osiToken);
        $credentialsVO->lock();

        return $credentialsVO;
    }
}
