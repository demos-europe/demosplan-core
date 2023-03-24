<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Rpc;

use DemosEurope\DemosplanAddon\Logic\Rpc\RpcErrorGeneratorInterface;
use Psr\Log\LoggerInterface;
use stdClass;

class RpcErrorGenerator implements RpcErrorGeneratorInterface
{
    public const ACCESS_DENIED_CODE = -32768;
    public const ACCESS_DENIED_MESSAGE = 'Access denied';

    public const INTERNAL_ERROR_CODE = -32603;
    public const INTERNAL_ERROR_MESSAGE = 'Internal error';

    public const INVALID_PARAMS_CODE = -32602;
    public const INVALID_PARAMS_MESSAGE = 'Invalid params';

    public const INVALID_REQUEST_CODE = -32600;
    public const INVALID_REQUEST_MESSAGE = 'Invalid Request';

    public const METHOD_NOT_FOUND_CODE = -32601;
    public const METHOD_NOT_FOUND_MESSAGE = 'Method not found';

    public const PARSE_ERROR_CODE = -32700;
    public const PARSE_ERROR_MESSAGE = 'Parse error';

    public const SERVER_ERROR_CODE = -32000;
    public const SERVER_ERROR_MESSAGE = 'Server error';

    /**
     * @var array
     *
     * @param object|null $rpcRequest
     *
     * @return array
     */
    private $errorMessages;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
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
        $this->logger = $logger;
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
