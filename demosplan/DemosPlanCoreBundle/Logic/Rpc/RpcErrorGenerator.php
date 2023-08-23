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
            self::ACCESS_DENIED_CODE    => self::ACCESS_DENIED_MESSAGE,
            self::INTERNAL_ERROR_CODE   => self::INTERNAL_ERROR_MESSAGE,
            self::INVALID_PARAMS_CODE   => self::INVALID_PARAMS_MESSAGE,
            self::INVALID_REQUEST_CODE  => self::INVALID_REQUEST_MESSAGE,
            self::METHOD_NOT_FOUND_CODE => self::METHOD_NOT_FOUND_MESSAGE,
            self::PARSE_ERROR_CODE      => self::PARSE_ERROR_MESSAGE,
            self::SERVER_ERROR_CODE     => self::SERVER_ERROR_MESSAGE,
        ];
    }

    public function parseError(?object $rpcRequest = null): object
    {
        return $this->generate(self::PARSE_ERROR_CODE, $rpcRequest);
    }

    public function invalidRequest(?object $rpcRequest = null): object
    {
        return $this->generate(self::INVALID_REQUEST_CODE, $rpcRequest);
    }

    public function methodNotFound(?object $rpcRequest = null): object
    {
        return $this->generate(self::METHOD_NOT_FOUND_CODE, $rpcRequest);
    }

    public function invalidParams(?object $rpcRequest = null): object
    {
        return $this->generate(self::INVALID_PARAMS_CODE, $rpcRequest);
    }

    public function internalError(?object $rpcRequest = null): object
    {
        return $this->generate(self::INTERNAL_ERROR_CODE, $rpcRequest);
    }

    public function serverError(?object $rpcRequest = null): object
    {
        return $this->generate(self::SERVER_ERROR_CODE, $rpcRequest);
    }

    public function accessDenied(?object $rpcRequest = null): object
    {
        return $this->generate(self::ACCESS_DENIED_CODE, $rpcRequest);
    }

    /**
     * @param object|null $rpcRequest
     */
    public function generate(int $errorCode, $rpcRequest = null): object
    {
        if (!array_key_exists($errorCode, $this->errorMessages)) {
            $error = ['code' => -32000, 'message' => self::SERVER_ERROR_MESSAGE];
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
