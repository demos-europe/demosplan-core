<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UserMapper implements UserMapperInterface
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        RequestStack $requestStack,
        UserService $userService
    ) {
        $this->requestStack = $requestStack;
        $this->userService = $userService;
    }

    /**
     * @throws Exception
     */
    public function getValidUser(Credentials $credentials): ?User
    {
        return $this->userService->getValidUser(trim($credentials->getLogin() ?? ''));
    }

    public function setRequestStack(RequestStack $requestStack): UserMapper
    {
        $this->requestStack = $requestStack;

        return $this;
    }

    protected function getRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new InvalidArgumentException('Request must be set Login Authentcation');
        }

        return $request;
    }
}
