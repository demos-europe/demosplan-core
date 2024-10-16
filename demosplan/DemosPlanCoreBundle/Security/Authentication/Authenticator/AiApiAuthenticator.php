<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\ChainTokenExtractor;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\QueryParameterTokenExtractor;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;

class AiApiAuthenticator extends JWTAuthenticator
{
    /**
     * Jwt payload may be given as URL query parameter "jwt".
     */
    final public const JWT_TOKEN_PARAMETER = 'jwt';

    protected function getTokenExtractor(): TokenExtractorInterface
    {
        /** @var ChainTokenExtractor $chainExtractor */
        $chainExtractor = parent::getTokenExtractor();

        // Add token extraction via query parameter
        // This allows us to send tokens to the external consumer in callback urls
        // Without them having to manually request them in an extra step
        $chainExtractor->addExtractor(new QueryParameterTokenExtractor(self::JWT_TOKEN_PARAMETER));

        return $chainExtractor;
    }
}
