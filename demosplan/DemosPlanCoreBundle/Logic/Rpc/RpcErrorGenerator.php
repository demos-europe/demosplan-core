<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Rpc;

use DemosEurope\DemosplanAddon\Logic\Rpc\RpcErrorGeneratorInterface;
use Psr\Log\LoggerInterface;
use stdClass;

class RpcErrorGenerator implements RpcErrorGeneratorInterface
{
    /**
     * @var array
     *
     * @param object|null $rpcRequest
     *
     * @return array
     */
    private $errorMessages;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->errorMessages = [
            RpcErrorGeneratorInterface::ACCESS_DENIED_CODE    => RpcErrorGeneratorInterface::ACCESS_DENIED_MESSAGE,
            RpcErrorGeneratorInterface::INTERNAL_ERROR_CODE   => RpcErrorGeneratorInterface::INTERNAL_ERROR_MESSAGE,
            RpcErrorGeneratorInterface::INVALID_PARAMS_CODE   => RpcErrorGeneratorInterface::INVALID_PARAMS_MESSAGE,
            RpcErrorGeneratorInterface::INVALID_REQUEST_CODE  => RpcErrorGeneratorInterface::INVALID_REQUEST_MESSAGE,
            RpcErrorGeneratorInterface::METHOD_NOT_FOUND_CODE => RpcErrorGeneratorInterface::METHOD_NOT_FOUND_MESSAGE,
            RpcErrorGeneratorInterface::PARSE_ERROR_CODE      => RpcErrorGeneratorInterface::PARSE_ERROR_MESSAGE,
            RpcErrorGeneratorInterface::SERVER_ERROR_CODE     => RpcErrorGeneratorInterface::SERVER_ERROR_MESSAGE,
        ];
    }

    public function parseError(?object $rpcRequest = null): object
    {
        return $this->generate(RpcErrorGeneratorInterface::PARSE_ERROR_CODE, $rpcRequest);
    }

    public function invalidRequest(?object $rpcRequest = null): object
    {
        return $this->generate(RpcErrorGeneratorInterface::INVALID_REQUEST_CODE, $rpcRequest);
    }

    public function methodNotFound(?object $rpcRequest = null): object
    {
        return $this->generate(RpcErrorGeneratorInterface::METHOD_NOT_FOUND_CODE, $rpcRequest);
    }

    public function invalidParams(?object $rpcRequest = null): object
    {
        return $this->generate(RpcErrorGeneratorInterface::INVALID_PARAMS_CODE, $rpcRequest);
    }

    public function internalError(?object $rpcRequest = null): object
    {
        return $this->generate(RpcErrorGeneratorInterface::INTERNAL_ERROR_CODE, $rpcRequest);
    }

    public function serverError(?object $rpcRequest = null): object
    {
        return $this->generate(RpcErrorGeneratorInterface::SERVER_ERROR_CODE, $rpcRequest);
    }

    public function accessDenied(?object $rpcRequest = null): object
    {
        return $this->generate(RpcErrorGeneratorInterface::ACCESS_DENIED_CODE, $rpcRequest);
    }

    /**
     * @param object|null $rpcRequest
     */
    public function generate(int $errorCode, $rpcRequest = null): object
    {
        if (!array_key_exists($errorCode, $this->errorMessages)) {
            $error = ['code' => -32000, 'message' => RpcErrorGeneratorInterface::SERVER_ERROR_MESSAGE];
        } else {
            $error = [
                'code'    => $errorCode,
                'message' => $this->errorMessages[$errorCode],
            ];
        }

        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->error = $error;
        $result->id = isset($rpcRequest) && isset($rpcRequest->id) ? $rpcRequest->id : null;

        $this->logger->warning('RPC Error', ['error' => $result->error]);

        return $result;
    }
}
