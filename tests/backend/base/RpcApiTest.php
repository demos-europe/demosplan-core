<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class RpcApiTest extends AbstractApiTest
{
    protected function executeRpcRequest(
        string $methodName,
        string $requestId,
        User $user,
        array $rpcParameters,
        Procedure $procedure = null,
        int $expectedStatus = Response::HTTP_OK
    ): array {
        // prepare and issue request
        $requestBody = [
            'jsonrpc' => '2.0',
            'method'  => $methodName,
            'params'  => $rpcParameters,
            'id'      => $requestId,
        ];
        $urlPath = $this->router->generate('rpc_generic_post', [], RouterInterface::RELATIVE_PATH);
        $response = $this->sendRequest($urlPath, Request::METHOD_POST, $user, $procedure, $requestBody);

        // validate and return
        self::assertSame($expectedStatus, $response->getStatusCode());
        $content = $response->getContent();

        return Json::decodeToArray($content);
    }

    protected function getServerParameters(): array
    {
        return [];
    }
}
