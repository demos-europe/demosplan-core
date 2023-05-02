<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Routing\RouterInterface;

class JwtRouter extends Router
{
    /**
     * @var JWTTokenManagerInterface
     */
    private $jwtManager;

    /**
     * This router decorates Symfony\Bundle\FrameworkBundle\Routing.
     */
    public function __construct(
        GlobalConfigInterface $globalConfig,
        JWTTokenManagerInterface $jwtManager,
        ProcedureRepository $procedureRepository,
        RouterInterface $router,
        protected readonly UserService $userService
    ) {
        $this->jwtManager = $jwtManager;
        parent::__construct($globalConfig, $procedureRepository, $router);
    }

    public function generate($route, $parameters = [], $referenceType = self::ABSOLUTE_URL): string
    {
        $aiApiUser = $this->userService->getValidUser(AiApiUser::AI_API_USER_LOGIN);
        $apiAuthorization = $this->jwtManager->create($aiApiUser);
        if (!array_key_exists('jwt', $parameters)) {
            $parameters['jwt'] = $apiAuthorization;
        }

        return parent::generate($route, $parameters, $referenceType);
    }
}
