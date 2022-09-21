<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector;

use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\Exception\MaillaneConfigurationException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * A specialized URL generator for Maillane.
 *
 * This can be used to generate the urls for interacting
 * with a coupled Maillane instance.
 *
 * The urls are absolute based on the configuration
 * parameter `maillane_api_baseurl` which should always
 * be a full http(s) base url to a Maillane instance.
 *
 * If you can open that URL (given access to the
 * configured network) and receive 'OK. BYE.' as
 * a response, it is the correct URL.
 *
 * The route methods are named after their common
 * {json:api} names, i.e. the `create` action is a `POST`
 * on the `list` route of a resource.
 *
 * While Maillane has more exposed routes, all other ones
 * are intrinsically available via it's API responses and
 * thus never have to be generated.
 */
class MaillaneRouter
{
    private const ACCOUNT_LIST = 'account_list';

    private const ACCOUNT_DETAIL = 'account_detail';

    private const USER_LIST = 'user_list';

    private const USER_DETAIL = 'user_detail';

    /**
     * @var UrlGeneratorInterface
     */
    private $generator;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        if (!$parameterBag->has('maillane_api_baseurl')) {
            throw MaillaneConfigurationException::missingParameter('maillane_api_baseurl');
        }

        $routes = new RouteCollection();
        $routes->add(self::ACCOUNT_LIST, new Route('/api/account/'));
        $routes->add(self::ACCOUNT_DETAIL, new Route('/api/account/{accountId}/'));
        $routes->add(self::USER_LIST, new Route('/api/account/{accountId}/user/'));
        $routes->add(self::USER_DETAIL, new Route('/api/account/{accountId}/user/{userId}/'));

        $requestContext = RequestContext::fromUri($parameterBag->get('maillane_api_baseurl'));

        $this->generator = new UrlGenerator($routes, $requestContext);
    }

    /**
     * Creates URL for creating or listing accounts
     *
     * @return string
     */
    public function accountList(): string
    {
        return $this->generator->generate(self::ACCOUNT_LIST, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Creates URL for deleting accounts or getting account details
     *
     * @return string
     */
    public function accountDetail(string $accountId): string
    {
        return $this->generator->generate(self::ACCOUNT_DETAIL, compact('accountId'), UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Creates URL for creating or listing users(allowed senders) for an account
     *
     * @return string
     */
    public function userList(string $accountId): string
    {
        return $this->generator->generate(self::USER_LIST, compact('accountId'), UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Creates URL for deleting users(allowed senders) or getting user(allowed senders) details
     *
     * @return string
     */
    public function userDetail(string $accountId, string $userId): string
    {
        return $this->generator->generate(self::USER_DETAIL, compact('accountId', 'userId'), UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
