<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Routing\RouterInterface;

class JwtRouter extends Router
{
    /**
     * This router decorates Symfony\Bundle\FrameworkBundle\Routing.
     */
    public function __construct(
        GlobalConfigInterface $globalConfig,
        private readonly JWTTokenManagerInterface $jwtManager,
        ProcedureRepository $procedureRepository,
        RouterInterface $router,
    ) {
        parent::__construct($globalConfig, $procedureRepository, $router);
    }

    public function generate($route, $parameters = [], $referenceType = self::ABSOLUTE_URL): string
    {
        $apiAuthorization = $this->jwtManager->create(new AiApiUser());
        if (!array_key_exists('jwt', $parameters)) {
            $parameters['jwt'] = $apiAuthorization;
        }

        return parent::generate($route, $parameters, $referenceType);
    }
}
