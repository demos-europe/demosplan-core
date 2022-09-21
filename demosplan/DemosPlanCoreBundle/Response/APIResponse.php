<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class APIResponse extends JsonResponse
{
    public function __construct($data = null, $status = 200, $headers = [], $json = false)
    {
        parent::__construct($data, $status, $headers, $json);

        $this->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_OBJECT_AS_ARRAY);
        $this->headers->set('Content-Type', 'application/vnd.api+json; charset=utf-8');
    }
}
