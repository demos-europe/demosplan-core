<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InvalidDataException extends DemosException
{
    private Request $request;
    private int $statusCode;

    public function __construct(
        string $message,
        Request $request,
        int $statusCode = Response::HTTP_BAD_REQUEST
    ) {
        parent::__construct($message);
        $this->request = $request;
        $this->statusCode = $statusCode;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
